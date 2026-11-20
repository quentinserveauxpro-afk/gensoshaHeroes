<?php

namespace App\Simulation;

use App\Enum\MissionStatut;
use App\Movement\HeroMovementService;
use App\Repository\HeroMovementRepository;
use App\Repository\MissionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;

/**
 * Un passage complet du moteur temps réel (appelé par app:missions:update ou par Messenger).
 *
 * Pour chaque mission en cours, sous un verrou propre à la mission (symfony/lock) :
 *   1. démarrage des déplacements manquants (rattrapage après un déploiement) ;
 *   2. avancement des déplacements et détection des arrivées ;
 *   3. étapes de simulation (MissionSimulationService) ;
 *   4. flush.
 * Une mission déjà verrouillée par un autre processus est ignorée : jamais de double traitement.
 * Les déplacements encore actifs vers une mission close ou annulée sont interrompus.
 */
final class MissionUpdateRunner
{
    public const TTL_VERROU = 60.0;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MissionRepository $missions,
        private readonly HeroMovementRepository $mouvementsRepo,
        private readonly HeroMovementService $mouvements,
        private readonly MissionSimulationService $simulation,
        private readonly LockFactory $verrous,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function executer(): RapportExecution
    {
        $rapport = new RapportExecution();
        $now = $this->clock->now();

        foreach ($this->missions->filtrer([MissionStatut::EN_COURS]) as $mission) {
            $verrou = $this->verrous->createLock('mission-simulation-'.$mission->getId(), self::TTL_VERROU);
            if (!$verrou->acquire(false)) {
                ++$rapport->missionsVerrouillees;
                continue;
            }
            try {
                $this->em->refresh($mission);
                if (MissionStatut::EN_COURS !== $mission->getStatut()) {
                    continue;
                }
                $rapport->deplacementsCrees += \count($this->mouvements->demarrerPourMission($mission));
                $this->em->flush();

                foreach ($this->mouvementsRepo->findPourMission($mission) as $mouvement) {
                    if (!$mouvement->isEnCours()) {
                        continue;
                    }
                    ++$rapport->deplacementsActualises;
                    if ($this->mouvements->actualiser($mouvement, $now)) {
                        ++$rapport->arrivees;
                    }
                }
                $this->em->flush();

                $resultat = $this->simulation->simuler($mission, $now);
                $this->em->flush();
                ++$rapport->missionsSimulees;
                $rapport->etapes += $resultat->etapes;
                $rapport->missionsTerminees += (int) $resultat->terminee;
            } catch (\Throwable $e) {
                // Aucune donnée personnelle dans le journal : identifiant de mission et type d'erreur uniquement.
                $this->logger->error('Simulation de la mission {id} interrompue : {erreur}', ['id' => $mission->getId(), 'erreur' => $e->getMessage()]);
                if (!$this->em->isOpen()) {
                    throw $e;
                }
            } finally {
                $verrou->release();
            }
        }

        foreach ($this->mouvementsRepo->findEnCours() as $mouvement) {
            if (MissionStatut::EN_COURS !== $mouvement->getMission()->getStatut()) {
                $mouvement->interrompre($now);
                ++$rapport->deplacementsInterrompus;
            }
        }
        $this->em->flush();

        return $rapport;
    }
}
