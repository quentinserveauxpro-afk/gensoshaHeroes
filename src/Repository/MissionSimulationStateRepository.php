<?php

namespace App\Repository;

use App\Entity\Mission;
use App\Entity\MissionSimulationState;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<MissionSimulationState> */
class MissionSimulationStateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MissionSimulationState::class);
    }

    public function findPourMission(Mission $mission): ?MissionSimulationState
    {
        return $this->findOneBy(['mission' => $mission]);
    }

    /**
     * @param list<int> $missionIds
     *
     * @return array<int, MissionSimulationState> indexé par id de mission
     */
    public function indexerParMission(array $missionIds): array
    {
        if ([] === $missionIds) {
            return [];
        }
        $index = [];
        foreach ($this->createQueryBuilder('s')->andWhere('IDENTITY(s.mission) IN (:ids)')->setParameter('ids', $missionIds)->getQuery()->getResult() as $etat) {
            $index[$etat->getMission()->getId()] = $etat;
        }

        return $index;
    }
}
