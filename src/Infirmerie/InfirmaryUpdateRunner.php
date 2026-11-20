<?php

namespace App\Infirmerie;

use App\Entity\Hero;
use App\Enum\TypeAdmission;
use App\Repository\HeroRepository;
use App\Repository\SejourInfirmerieRepository;
use App\Simulation\MissionUpdateRunner;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;

/**
 * Un passage de l'infirmerie (app:infirmary:update, worker des missions ou Messenger).
 *
 *  1. rapatriement automatique des héros en état critique sans séjour actif ; un héros engagé
 *     sur une mission n'est traité que si le verrou de cette mission est libre (pas de
 *     concurrence avec le moteur de simulation) ;
 *  2. retours au QG, récupération et sorties de tous les séjours actifs (requêtes groupées) ;
 *  3. alerte « infirmerie complète » si la capacité est atteinte ;
 *  4. flush (transactionnel).
 * Verrou global « app-infirmary-update » : deux passages ne s'exécutent jamais en parallèle.
 */
final class InfirmaryUpdateRunner
{
    public const VERROU = 'app-infirmary-update';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly HeroRepository $heroes,
        private readonly SejourInfirmerieRepository $sejours,
        private readonly HeroRepatriationService $rapatriement,
        private readonly HeroRecoveryService $recuperation,
        private readonly InfirmerieAlerteService $alertes,
        private readonly InfirmerieConfiguration $config,
        private readonly QgConfiguration $qg,
        private readonly HeroMedicalEvaluationService $medical,
        private readonly \App\Geo\DistanceCalculator $distances,
        private readonly LockFactory $verrous,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function executer(): RapportInfirmerie
    {
        $rapport = new RapportInfirmerie();
        $verrou = $this->verrous->createLock(self::VERROU, MissionUpdateRunner::TTL_VERROU);
        if (!$verrou->acquire(false)) {
            $rapport->verrouille = true;

            return $rapport;
        }
        try {
            $now = $this->clock->now();
            $this->admettreHerosCritiques($now, $rapport);
            $this->actualiserSejours($now, $rapport);
            $this->alertes->infirmerieComplete($this->sejours->compterActifs(), $this->config->capacite, $now);
            $this->em->flush();
        } finally {
            $verrou->release();
        }

        return $rapport;
    }

    private function admettreHerosCritiques(\DateTimeImmutable $now, RapportInfirmerie $rapport): void
    {
        $actifs = $this->sejours->indexerActifsParHero();
        foreach ($this->heroes->findAll() as $hero) {
            if (isset($actifs[$hero->getId()]) || null === $this->medical->motifRapatriement($hero)) {
                continue;
            }
            $mission = $hero->getAffectationActive()?->getMission();
            $verrou = null === $mission ? null : $this->verrous->createLock('mission-simulation-'.$mission->getId(), MissionUpdateRunner::TTL_VERROU);
            if (null !== $verrou && !$verrou->acquire(false)) {
                ++$rapport->ignores;
                continue;
            }
            try {
                if (null !== $this->rapatriement->rapatrierSiNecessaire($hero, $now, TypeAdmission::AUTOMATIQUE)) {
                    ++$rapport->admissions;
                }
                $this->em->flush();
            } catch (\Throwable $e) {
                $this->logger->error('Rapatriement du héros {id} interrompu : {erreur}', ['id' => $hero->getId(), 'erreur' => $e->getMessage()]);
                if (!$this->em->isOpen()) {
                    throw $e;
                }
            } finally {
                $verrou?->release();
            }
        }
    }

    private function actualiserSejours(\DateTimeImmutable $now, RapportInfirmerie $rapport): void
    {
        $sejours = $this->sejours->findActifs();
        $contexte = new ContexteRecuperation($this->soutiensDisponibles($sejours), \count($sejours));
        foreach ($sejours as $sejour) {
            ++$rapport->sejoursActualises;
            foreach ($this->recuperation->actualiser($sejour, $now, $contexte) as $evenement) {
                match ($evenement) {
                    'arrivee' => ++$rapport->arrivees,
                    'retabli' => ++$rapport->retablis,
                    'sorti' => ++$rapport->sorties,
                    default => null,
                };
            }
        }
    }

    /**
     * Héros disponibles au QG disposant de l'aptitude de soutien médical (une requête).
     *
     * @param list<\App\Entity\SejourInfirmerie> $sejours
     *
     * @return list<int>
     */
    public function soutiensDisponibles(array $sejours = []): array
    {
        $patients = array_map(static fn ($s) => (int) $s->getHero()->getId(), $sejours);
        $qg = $this->qg->coordonnees();

        return array_values(array_map(static fn (Hero $h) => (int) $h->getId(), array_filter(
            $this->heroes->findAvecAptitude($this->config->aptitudeSoutien),
            fn (Hero $h) => $h->isDisponible() && !\in_array((int) $h->getId(), $patients, true)
                && (null === $h->getCoordonnees() || $this->distances->distanceKm($h->getCoordonnees(), $qg) <= QgConfiguration::RAYON_KM),
        )));
    }
}
