<?php

namespace App\Infirmerie;

use App\Entity\GeoPosition;
use App\Entity\SejourInfirmerie;
use App\Enum\HeroStatut;
use App\Enum\NiveauAlerte;
use App\Enum\SourceGeolocalisation;
use App\Enum\StatutSejour;
use App\GameTime\InfirmaryRecoveryTimeCalculator;
use App\Geo\Coordonnees;
use App\Geo\DistanceCalculator;
use App\Geo\EstimatedArrivalCalculator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Retour au QG et récupération des héros, calculés côté serveur à partir des dates enregistrées.
 *
 * Retour : position = fonction pure du temps ; aucune récupération pendant le trajet ; l'arrivée
 * est actée à l'heure prévue, même si le worker passe en retard.
 *
 * Règle de sortie : points de vie == maximum ET fatigue == 0 (les deux sont obligatoires).
 *
 * Traitement : à l'arrivée, InfirmaryRecoveryTimeCalculator fixe une durée pour la santé et
 * une pour la fatigue ; la sortie prévue est la fin de la plus longue (1 à 15 min). Chaque
 * composante progresse linéairement à son propre rythme (enregistré dans les taux du séjour)
 * et s'arrête à sa cible :
 *
 *   santé   = min(maximum, santé enregistrée   + rythme santé   × (maintenant − dernierCalcul))
 *   fatigue = max(0,       fatigue enregistrée − rythme fatigue × (maintenant − dernierCalcul))
 *
 * Santé au maximum avant la fatigue : la santé s'arrête, la fatigue continue de baisser ;
 * fatigue nulle avant la santé : l'inverse. Le rythme effectif n'est jamais inférieur à celui
 * qui atteint la cible à la sortie prévue. Le séjour passe « rétabli » quand les deux cibles
 * sont atteintes (à la date prévue, exactement). La santé ne diminue jamais, la fatigue
 * n'augmente jamais. Calculs à la seconde entière : relancer au même instant ne change rien.
 */
final class HeroRecoveryService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly InfirmerieConfiguration $config,
        private readonly HeroMedicalEvaluationService $medical,
        private readonly QgConfiguration $qg,
        private readonly DistanceCalculator $distances,
        private readonly EstimatedArrivalCalculator $eta,
        private readonly InfirmaryRecoveryTimeCalculator $dureeSoin,
        private readonly InfirmerieAlerteService $alertes,
        private readonly ?InfirmerieContexteProvider $contextes = null,
    ) {
    }

    /** Position du héros pendant son retour (calcul pur), ou null s'il n'est pas en route. */
    public function positionRetour(SejourInfirmerie $sejour, \DateTimeImmutable $now): ?Coordonnees
    {
        $depart = $sejour->getDepartRetour();
        if (StatutSejour::RETOUR_QG !== $sejour->getStatut() || null === $depart || null === $sejour->getArriveeQgPrevueLe()) {
            return null;
        }

        return $this->distances->pointIntermediaire($depart, $this->qg->coordonnees(), $this->progressionRetour($sejour, $now));
    }

    public function progressionRetour(SejourInfirmerie $sejour, \DateTimeImmutable $now): float
    {
        if (null === $sejour->getArriveeQgPrevueLe()) {
            return StatutSejour::RETOUR_QG === $sejour->getStatut() ? 0.0 : 1.0;
        }

        return $this->eta->fraction($sejour->getDepartRetourLe() ?? $now, $sejour->getArriveeQgPrevueLe(), $now);
    }

    /**
     * Fait évoluer un séjour actif jusqu'à $now (idempotent).
     *
     * @return list<string> événements : arrivee, retabli, sorti
     */
    public function actualiser(SejourInfirmerie $sejour, \DateTimeImmutable $now, ContexteRecuperation $contexte): array
    {
        $now = self::secondeEntiere($now);
        $evenements = [];
        if (StatutSejour::RETOUR_EN_ATTENTE === $sejour->getStatut()) {
            $this->arriver($sejour, $now, $contexte);
            $evenements[] = 'arrivee';
        }
        if (StatutSejour::RETOUR_QG === $sejour->getStatut()) {
            $prevue = $sejour->getArriveeQgPrevueLe();
            if (null !== $prevue && $prevue > $now) {
                return $evenements;
            }
            $this->arriver($sejour, self::secondeEntiere(null === $prevue ? $now : min($now, $prevue)), $contexte);
            $evenements[] = 'arrivee';
        }
        if ($this->enRecuperation($sejour) && $this->recuperer($sejour, $now, $contexte)) {
            $evenements[] = 'retabli';
        }
        if (StatutSejour::RETABLI === $sejour->getStatut() && $this->config->sortieAutomatique && $this->sortieAutorisee($sejour)) {
            $this->sortir($sejour, $sejour->getRetabliLe() ?? $now);
            $evenements[] = 'sorti';
        }

        return $evenements;
    }

    /** Arrivée au QG : début du traitement, durée fixée, héros repositionné sur le QG. */
    public function arriver(SejourInfirmerie $sejour, \DateTimeImmutable $date, ?ContexteRecuperation $contexte = null): void
    {
        $sejour->arriverAuQg($date);
        $this->planifier($sejour, $date, $contexte ?? $this->contextes?->contexte(null, null === $sejour->getId() ? 1 : 0) ?? new ContexteRecuperation());

        $hero = $sejour->getHero();
        $position = $hero->getGeoPosition();
        if (null === $position) {
            $position = new GeoPosition($this->qg->coordonnees(), SourceGeolocalisation::DEPLACEMENT);
            $hero->setGeoPosition($position);
            $this->em->persist($position);
        } else {
            $position->setCoordonnees($this->qg->coordonnees(), SourceGeolocalisation::DEPLACEMENT);
        }
        $position->setLibelle($this->qg->nom);

        $this->alertes->pourSejour($sejour, InfirmerieAlerteService::ARRIVEE, 'Arrivée au QG',
            sprintf('%s est arrivé à l\'infirmerie (%s) : traitement en cours.', $hero->getNom(), $this->qg->nom), NiveauAlerte::INFO, $date);
    }

    /**
     * Planifie la récupération depuis $debut : rythme de chaque composante et sortie prévue.
     * À l'arrivée ($arrivee), la durée totale du calculateur s'applique (1 minute au moins) ;
     * pour une reprise ou une normalisation, seul le travail restant compte.
     */
    public function planifier(SejourInfirmerie $sejour, \DateTimeImmutable $debut, ContexteRecuperation $contexte, bool $arrivee = true): void
    {
        [$sante, $fatigue] = $this->depart($sejour);
        [$cibleSante, $cibleFatigue] = $this->cibles($sejour, $sante, $fatigue);
        $plan = $this->planRestant($sejour, $contexte, $debut, $arrivee);
        // Rythmes par minute réelle (colonnes « taux » du séjour).
        $sejour->setTaux(
            $plan['sante'] > 0 ? ($cibleSante - $sante) / $plan['sante'] * 60 : 0.0,
            $plan['fatigue'] > 0 ? ($fatigue - $cibleFatigue) / $plan['fatigue'] * 60 : 0.0,
        );
        $sejour->enregistrerRecuperation($sante, $fatigue, $debut, $debut->modify(sprintf('+%d seconds', $plan['total'])));
    }

    /**
     * Temps nécessaire (secondes) pour la santé, pour la fatigue, et avant la sortie (la plus
     * longue des deux), à partir des valeurs enregistrées. Calcul pur, sans écriture.
     *
     * @return array{sante: int, fatigue: int, total: int}
     */
    public function planRestant(SejourInfirmerie $sejour, ContexteRecuperation $contexte, \DateTimeImmutable $now, bool $arrivee = false): array
    {
        [$sante, $fatigue] = $this->depart($sejour);
        [$cibleSante, $cibleFatigue] = $this->cibles($sejour, $sante, $fatigue);
        $durees = $this->dureeSoin->durees($sejour, $contexte, $sejour->getDebutTraitementLe() ?? $now);
        $max = max(0, $sejour->getHero()->getPointsDeVieMaximum());
        $manqueAdmission = $max - HeroMedicalEvaluationService::bornerSante($sejour->getSanteAdmission(), $max);
        $fatigueAdmission = HeroMedicalEvaluationService::bornerFatigue($sejour->getFatigueAdmission());

        $resteSante = $cibleSante - $sante <= 1e-6 ? 0 : (int) ceil($durees['sante'] > 0 && $manqueAdmission > 1e-6
            ? $durees['sante'] * min(1.0, ($cibleSante - $sante) / $manqueAdmission)
            : $durees['total']);
        $resteFatigue = $fatigue - $cibleFatigue <= 1e-6 ? 0 : (int) ceil($durees['fatigue'] > 0 && $fatigueAdmission > 1e-6
            ? $durees['fatigue'] * min(1.0, ($fatigue - $cibleFatigue) / $fatigueAdmission)
            : $durees['total']);

        return [
            'sante' => $resteSante,
            'fatigue' => $resteFatigue,
            'total' => max($resteSante, $resteFatigue, $arrivee ? $durees['total'] : 0),
        ];
    }

    /**
     * État projeté à $now sans écriture (santé, fatigue, progression globale du soin de 0 à 1).
     *
     * @return array{0: float, 1: float, 2: float}
     */
    public function etatA(SejourInfirmerie $sejour, \DateTimeImmutable $now): array
    {
        [$sante, $fatigue] = $this->depart($sejour);
        $now = self::secondeEntiere($now);
        $debut = $sejour->getDebutTraitementLe();
        $fin = $sejour->getSortiePrevueLe();
        if (StatutSejour::RETABLI === $sejour->getStatut() && $this->valeursCompletes($sejour)) {
            return [$sante, $fatigue, 1.0];
        }
        if (!$this->enRecuperation($sejour) || null === $fin || $this->repriseAPlanifier($sejour)) {
            return [$sante, $fatigue, 0.0];
        }
        [$cibleSante, $cibleFatigue] = $this->cibles($sejour, $sante, $fatigue);
        $global = null === $debut ? self::fraction($now, $fin, $now) : self::fraction($debut, $fin, $now);
        if ($now >= $fin) {
            return [$cibleSante, $cibleFatigue, 1.0];
        }
        $depuis = $sejour->getDernierCalculLe() ?? $debut ?? $now;
        $ecoule = max(0, $now->getTimestamp() - $depuis->getTimestamp());
        [$rythmeSante, $rythmeFatigue] = $this->rythmes($sejour, $sante, $fatigue, $depuis, $fin);

        return [
            min($cibleSante, $sante + $rythmeSante * $ecoule),
            max($cibleFatigue, $fatigue - $rythmeFatigue * $ecoule),
            $global,
        ];
    }

    /**
     * Secondes restantes pour la santé, pour la fatigue, et avant la sortie (la plus longue).
     *
     * @return array{sante: int, fatigue: int, total: int}
     */
    public function restants(SejourInfirmerie $sejour, \DateTimeImmutable $now): array
    {
        $now = self::secondeEntiere($now);
        $fin = $sejour->getSortiePrevueLe();
        if (null === $fin || (StatutSejour::RETABLI === $sejour->getStatut() && $this->valeursCompletes($sejour))) {
            return ['sante' => 0, 'fatigue' => 0, 'total' => 0];
        }
        $avantSortie = max(0, $fin->getTimestamp() - $now->getTimestamp());
        [$sante, $fatigue] = $this->etatA($sejour, $now);
        [$cibleSante, $cibleFatigue] = $this->cibles($sejour, ...$this->depart($sejour));
        [$rythmeSante, $rythmeFatigue] = $this->rythmes($sejour, ...$this->depart($sejour), ...[$sejour->getDernierCalculLe() ?? $now, $fin]);
        $reste = static fn (float $ecart, float $rythme): int => $ecart <= 1e-6 ? 0 : ($rythme > 0 ? min($avantSortie, (int) ceil($ecart / $rythme - 1e-6)) : $avantSortie);

        return [
            'sante' => $reste($cibleSante - $sante, $rythmeSante),
            'fatigue' => $reste($fatigue - $cibleFatigue, $rythmeFatigue),
            'total' => $avantSortie,
        ];
    }

    /** @return bool vrai si le héros vient d'être déclaré rétabli (santé maximale ET fatigue nulle) */
    private function recuperer(SejourInfirmerie $sejour, \DateTimeImmutable $now, ContexteRecuperation $contexte): bool
    {
        if (null === $sejour->getSortiePrevueLe()) {
            // Séjour antérieur à l'échelle accélérée sans date de fin : planifié depuis maintenant.
            $this->planifier($sejour, $now, $contexte);
        } elseif ($this->repriseAPlanifier($sejour)) {
            // Déclaré rétabli selon l'ancienne règle (fatigue restante) : la récupération reprend.
            $this->planifier($sejour, $now, $contexte, false);
        }
        $fin = $sejour->getSortiePrevueLe();

        if ($now >= $fin) {
            [$cibleSante, $cibleFatigue] = $this->cibles($sejour, ...$this->depart($sejour));
            $sejour->enregistrerRecuperation($cibleSante, $cibleFatigue, $fin, $fin);
            $this->synchroniserHero($sejour);
            $hero = $sejour->getHero();
            if (!$this->medical->heroRetabli($hero)) {
                return false; // garde-fou : jamais « rétabli » sans santé maximale ET fatigue nulle
            }
            if (StatutSejour::EN_TRAITEMENT === $sejour->getStatut()) {
                $sejour->marquerRetabli($fin);
            }
            $this->alertes->pourSejour($sejour, InfirmerieAlerteService::RETABLI, 'Récupération terminée',
                sprintf('%s est totalement rétabli (%d / %d PV, fatigue 0)%s.', $hero->getNom(), (int) $hero->getPointsDeVie(), $hero->getPointsDeVieMaximum(),
                    $this->config->sortieAutomatique ? ' et quitte l\'infirmerie' : ' : sortie à valider par un opérateur héros'), NiveauAlerte::INFO, $fin);

            return true;
        }

        [$sante, $fatigue] = $this->etatA($sejour, $now);
        $sejour->enregistrerRecuperation($sante, $fatigue, $now, $fin);
        $this->synchroniserHero($sejour);

        return false;
    }

    /** Sortie autorisée : séjour rétabli ET points de vie == maximum ET fatigue == 0. */
    public function sortieAutorisee(SejourInfirmerie $sejour): bool
    {
        return StatutSejour::RETABLI === $sejour->getStatut()
            && $this->valeursCompletes($sejour)
            && $this->medical->heroRetabli($sejour->getHero());
    }

    /** Sortie de l'infirmerie : le héros redevient sélectionnable (sans réaffectation automatique). */
    public function sortir(SejourInfirmerie $sejour, \DateTimeImmutable $date): void
    {
        if (!$this->sortieAutorisee($sejour)) {
            $hero = $sejour->getHero();
            throw new InfirmerieException(sprintf('%s n\'est pas encore totalement rétabli (%d / %d PV, fatigue %d) : sortie impossible, il faut la santé au maximum et une fatigue à 0.',
                $hero->getNom(), (int) $hero->getPointsDeVie(), $hero->getPointsDeVieMaximum(), $hero->getFatigue()));
        }
        $sejour->sortir($date);
        $hero = $sejour->getHero();
        if (\in_array($hero->getStatut(), [HeroStatut::BLESSE, HeroStatut::REPOS], true)) {
            $hero->setStatut(HeroStatut::DISPONIBLE);
        }
    }

    /**
     * Secondes réelles restantes avant rétablissement complet (jamais négatif), null si non
     * planifié : la plus longue des deux récupérations (santé, fatigue).
     */
    public function secondesRestantes(SejourInfirmerie $sejour, \DateTimeImmutable $now): ?int
    {
        return null === $sejour->getSortiePrevueLe() ? null : $this->restants($sejour, $now)['total'];
    }

    /** Durée totale prévue du traitement (secondes), null si non planifié. */
    public function dureeTraitement(SejourInfirmerie $sejour): ?int
    {
        $debut = $sejour->getDebutTraitementLe();
        $fin = $sejour->getSortiePrevueLe();

        return null === $debut || null === $fin ? null : max(0, $fin->getTimestamp() - $debut->getTimestamp());
    }

    public static function secondeEntiere(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return $date->setTimestamp($date->getTimestamp());
    }

    public static function fraction(\DateTimeImmutable $debut, \DateTimeImmutable $fin, \DateTimeImmutable $now): float
    {
        $total = $fin->getTimestamp() - $debut->getTimestamp();
        if ($total <= 0) {
            return 1.0;
        }

        return max(0.0, min(1.0, ($now->getTimestamp() - $debut->getTimestamp()) / $total));
    }

    /** @return array{0: float, 1: float} valeurs enregistrées, bornées */
    private function depart(SejourInfirmerie $sejour): array
    {
        return [
            HeroMedicalEvaluationService::bornerSante($sejour->getSanteActuelle(), $sejour->getHero()->getPointsDeVieMaximum()),
            HeroMedicalEvaluationService::bornerFatigue($sejour->getFatigueActuelle()),
        ];
    }

    /**
     * Cibles de sortie : santé maximale et fatigue nulle, quelles que soient les cibles
     * enregistrées à l'admission (ancienne règle). La santé ne baisse jamais, la fatigue
     * n'augmente jamais.
     *
     * @return array{0: float, 1: float}
     */
    private function cibles(SejourInfirmerie $sejour, float $sante, float $fatigue): array
    {
        return [max($sante, (float) max(0, $sejour->getHero()->getPointsDeVieMaximum())), 0.0];
    }

    /**
     * Rythmes effectifs (par seconde) : rythme planifié, jamais inférieur à celui qui atteint
     * la cible à la sortie prévue.
     *
     * @return array{0: float, 1: float}
     */
    private function rythmes(SejourInfirmerie $sejour, float $sante, float $fatigue, \DateTimeImmutable $depuis, \DateTimeImmutable $fin): array
    {
        [$cibleSante, $cibleFatigue] = $this->cibles($sejour, $sante, $fatigue);
        $duree = max(1, $fin->getTimestamp() - $depuis->getTimestamp());

        return [
            max($sejour->getTauxSante() / 60, ($cibleSante - $sante) / $duree),
            max($sejour->getTauxFatigue() / 60, ($fatigue - $cibleFatigue) / $duree),
        ];
    }

    /** Valeurs enregistrées du séjour : santé maximale ET fatigue nulle. */
    private function valeursCompletes(SejourInfirmerie $sejour): bool
    {
        [$sante, $fatigue] = $this->depart($sejour);

        return HeroMedicalEvaluationService::estRetabli($sante, $sejour->getHero()->getPointsDeVieMaximum(), $fatigue);
    }

    /** En traitement, ou « rétabli » selon l'ancienne règle alors qu'il reste à récupérer. */
    private function enRecuperation(SejourInfirmerie $sejour): bool
    {
        return StatutSejour::EN_TRAITEMENT === $sejour->getStatut()
            || (StatutSejour::RETABLI === $sejour->getStatut() && !$this->valeursCompletes($sejour));
    }

    /** Séjour rétabli incomplet dont la reprise n'a pas encore été planifiée. */
    private function repriseAPlanifier(SejourInfirmerie $sejour): bool
    {
        $fin = $sejour->getSortiePrevueLe();
        $retabli = $sejour->getRetabliLe();

        return StatutSejour::RETABLI === $sejour->getStatut() && !$this->valeursCompletes($sejour)
            && (null === $fin || (null !== $retabli && $fin <= $retabli));
    }

    /** Les valeurs entières du héros dérivent des valeurs décimales du séjour (bornées). */
    private function synchroniserHero(SejourInfirmerie $sejour): void
    {
        $hero = $sejour->getHero();
        $max = max(0, $hero->getPointsDeVieMaximum());
        $hero->setPointsDeVie((int) floor(HeroMedicalEvaluationService::bornerSante($sejour->getSanteActuelle(), $max) + 1e-6));
        $hero->setFatigue((int) ceil(HeroMedicalEvaluationService::bornerFatigue($sejour->getFatigueActuelle()) - 1e-6));
    }
}
