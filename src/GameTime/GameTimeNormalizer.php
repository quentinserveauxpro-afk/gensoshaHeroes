<?php

namespace App\GameTime;

use App\Entity\HeroMovement;
use App\Entity\SejourInfirmerie;
use App\Enum\StatutSejour;
use App\Infirmerie\HeroMedicalEvaluationService;
use App\Infirmerie\HeroRecoveryService;
use App\Infirmerie\InfirmaryUpdateRunner;
use App\Infirmerie\InfirmerieContexteProvider;
use App\Infirmerie\QgConfiguration;
use App\Movement\HeroMovementService;
use App\Repository\HeroMovementRepository;
use App\Repository\SejourInfirmerieRepository;
use App\Simulation\MissionUpdateRunner;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Lock\LockFactory;

/**
 * Mise en conformité des opérations en cours avec l'échelle temporelle accélérée.
 *
 * - Trajets (missions, retours au QG) : progression acquise conservée (fraction f à cet
 *   instant) ; nouvelle durée D' = HeroTravelTimeCalculator ; départ = maintenant − f × D',
 *   arrivée = maintenant + (1 − f) × D'. Appliqué seulement si l'arrivée est avancée.
 * - Soins : progression acquise = min(progression santé, progression fatigue) déjà atteinte ;
 *   nouvelle fin = maintenant + (1 − p) × D' (InfirmaryRecoveryTimeCalculator) ; les valeurs
 *   enregistrées sont conservées. Appliqué seulement si la fin est avancée (ou absente).
 * - Échéance déjà dépassée : opération clôturée immédiatement (arrivée, rétablissement).
 * Jamais de suppression, jamais de rallongement. En simulation (dry-run), aucune écriture.
 */
final class GameTimeNormalizer
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly HeroMovementRepository $mouvements,
        private readonly SejourInfirmerieRepository $sejours,
        private readonly HeroMovementService $deplacements,
        private readonly HeroRecoveryService $recuperation,
        private readonly HeroTravelTimeCalculator $tempsTrajet,
        private readonly InfirmaryRecoveryTimeCalculator $tempsSoin,
        private readonly InfirmerieContexteProvider $contextes,
        private readonly LockFactory $verrous,
        private readonly ClockInterface $clock,
    ) {
    }

    /** @return list<array{type: string, id: int|null, hero: string, avant: ?\DateTimeImmutable, apres: ?\DateTimeImmutable, action: string}> */
    public function normaliser(bool $appliquer): array
    {
        $now = HeroRecoveryService::secondeEntiere($this->clock->now());
        $lignes = [];
        foreach ($this->mouvements->findEnCours() as $mouvement) {
            $lignes[] = $this->sousVerrou($appliquer, 'mission-simulation-'.$mouvement->getMission()->getId(), fn () => $this->trajet($mouvement, $now, $appliquer));
        }
        $actifs = $this->sejours->findActifs();
        $contexte = $this->contextes->contexte($actifs);
        foreach ($actifs as $sejour) {
            $ligne = $this->sousVerrou($appliquer, InfirmaryUpdateRunner::VERROU, fn () => match ($sejour->getStatut()) {
                StatutSejour::RETOUR_QG => $this->retour($sejour, $now, $appliquer),
                StatutSejour::EN_TRAITEMENT => $this->soin($sejour, $now, $appliquer, $contexte),
                default => null,
            });
            if (null !== $ligne) {
                $lignes[] = $ligne;
            }
        }

        return $lignes;
    }

    private function trajet(HeroMovement $m, \DateTimeImmutable $now, bool $appliquer): array
    {
        $ligne = ['type' => 'Trajet vers mission', 'id' => $m->getId(), 'hero' => (string) $m->getHero()->getNom(), 'avant' => $m->getDateArriveePrevue()];
        if ($m->getDateArriveePrevue() <= $now) {
            if ($appliquer) {
                $this->deplacements->actualiser($m, $now);
            }

            return $ligne + ['apres' => $m->getDateArriveePrevue(), 'action' => 'clôturé (échéance dépassée)'];
        }
        $duree = $this->tempsTrajet->duree($m->getHero(), $m->getDistanceKm(), $now, HeroMovementService::DISTANCE_SUR_PLACE_KM);
        [$depart, $arrivee] = $this->replanification($m->getDateDepart(), $m->getDateArriveePrevue(), $now, $duree);
        if (null === $arrivee) {
            return $ligne + ['apres' => $m->getDateArriveePrevue(), 'action' => 'inchangé'];
        }
        if ($appliquer) {
            $m->replanifier($depart, $arrivee, HeroTravelTimeCalculator::vitesseEquivalente($m->getDistanceKm(), $duree), $now);
            if ($arrivee <= $now) {
                $this->deplacements->actualiser($m, $now);
            }
        }

        return $ligne + ['apres' => $arrivee, 'action' => 'raccourci'];
    }

    private function retour(SejourInfirmerie $s, \DateTimeImmutable $now, bool $appliquer): array
    {
        $prevue = $s->getArriveeQgPrevueLe();
        $ligne = ['type' => 'Retour au QG', 'id' => $s->getId(), 'hero' => (string) $s->getHero()->getNom(), 'avant' => $prevue];
        if (null === $prevue || $prevue <= $now) {
            if ($appliquer) {
                $this->recuperation->actualiser($s, $now, $this->contextes->contexte());
            }

            return $ligne + ['apres' => $prevue, 'action' => 'clôturé (arrivée au QG)'];
        }
        $duree = $this->tempsTrajet->duree($s->getHero(), (float) $s->getDistanceRetourKm(), $now, QgConfiguration::RAYON_KM);
        [$depart, $arrivee] = $this->replanification($s->getDepartRetourLe() ?? $s->getAdmisLe(), $prevue, $now, $duree);
        if (null === $arrivee) {
            return $ligne + ['apres' => $prevue, 'action' => 'inchangé'];
        }
        if ($appliquer) {
            $s->replanifierRetour($depart, $arrivee, HeroTravelTimeCalculator::vitesseEquivalente((float) $s->getDistanceRetourKm(), $duree), $now);
        }

        return $ligne + ['apres' => $arrivee, 'action' => 'raccourci'];
    }

    private function soin(SejourInfirmerie $s, \DateTimeImmutable $now, bool $appliquer, \App\Infirmerie\ContexteRecuperation $contexte): array
    {
        $fin = $s->getSortiePrevueLe();
        $ligne = ['type' => 'Soin', 'id' => $s->getId(), 'hero' => (string) $s->getHero()->getNom(), 'avant' => $fin];
        if (null !== $fin && $fin <= $now) {
            if ($appliquer) {
                $this->recuperation->actualiser($s, $now, $contexte);
            }

            return $ligne + ['apres' => $fin, 'action' => 'clôturé (rétabli)'];
        }
        // Travail restant pour la santé et pour la fatigue ; sortie = la plus longue des deux.
        $restant = $this->recuperation->planRestant($s, $contexte, $now)['total'];
        $nouvelleFin = $now->modify(sprintf('+%d seconds', max(0, $restant)));
        if (null !== $fin && $nouvelleFin >= $fin->modify('-1 second')) {
            return $ligne + ['apres' => $fin, 'action' => 'inchangé'];
        }
        if ($appliquer) {
            // Valeurs acquises conservées ; rythmes et compte à rebours repartent de maintenant.
            $this->recuperation->planifier($s, $now, $contexte, false);
            if ($nouvelleFin <= $now) {
                $this->recuperation->actualiser($s, $now, $contexte);
            }
        }

        return $ligne + ['apres' => $nouvelleFin, 'action' => null === $fin ? 'planifié' : 'raccourci'];
    }

    /**
     * Fraction du soin déjà acquise (la plus faible entre santé et fatigue), dans [0, 1].
     * Cibles de sortie : santé maximale et fatigue nulle.
     */
    public function progressionAcquise(SejourInfirmerie $s): float
    {
        $max = $s->getHero()->getPointsDeVieMaximum();
        $santeDepart = HeroMedicalEvaluationService::bornerSante($s->getSanteAdmission(), $max);
        $cibleSante = (float) max(0, $max);
        $pSante = $cibleSante > $santeDepart ? ($s->getSanteActuelle() - $santeDepart) / ($cibleSante - $santeDepart) : 1.0;
        $fatigueDepart = HeroMedicalEvaluationService::bornerFatigue($s->getFatigueAdmission());
        $pFatigue = $fatigueDepart > 0 ? ($fatigueDepart - $s->getFatigueActuelle()) / $fatigueDepart : 1.0;

        return max(0.0, min(1.0, $pSante, $pFatigue));
    }

    /**
     * Nouveau calendrier conservant la fraction acquise, ou [null, null] s'il ne raccourcit pas.
     *
     * @return array{0: ?\DateTimeImmutable, 1: ?\DateTimeImmutable}
     */
    private function replanification(\DateTimeImmutable $depart, \DateTimeImmutable $arrivee, \DateTimeImmutable $now, int $duree): array
    {
        $f = HeroRecoveryService::fraction($depart, $arrivee, $now);
        $nouvelleArrivee = $now->modify(sprintf('+%d seconds', (int) ceil((1 - $f) * $duree)));
        if ($nouvelleArrivee >= $arrivee->modify('-1 second')) {
            return [null, null];
        }

        return [$now->modify(sprintf('-%d seconds', (int) floor($f * $duree))), $nouvelleArrivee];
    }

    /** Écriture sous le verrou du traitement concerné (attente du passage en cours du worker). */
    private function sousVerrou(bool $appliquer, string $cle, callable $operation): mixed
    {
        if (!$appliquer) {
            return $operation();
        }
        $verrou = $this->verrous->createLock($cle, MissionUpdateRunner::TTL_VERROU);
        $verrou->acquire(true);
        try {
            $resultat = $operation();
            $this->em->flush();

            return $resultat;
        } finally {
            $verrou->release();
        }
    }
}
