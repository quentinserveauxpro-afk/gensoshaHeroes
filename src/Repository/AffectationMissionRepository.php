<?php

namespace App\Repository;

use App\Entity\AffectationMission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Enum\MissionStatut;
use App\Enum\AffectationStatut;
use App\Entity\Mission;

/**
 * @extends ServiceEntityRepository<AffectationMission>
 */
class AffectationMissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AffectationMission::class);
    }

    /**
     * Affectations actives (dans une mission active) des héros donnés, hors mission exclue.
     *
     * @param list<int> $heroIds
     *
     * @return list<AffectationMission>
     */
    public function findActivesPourHeros(array $heroIds, ?Mission $exclure = null): array
    {
        if ([] === $heroIds) {
            return [];
        }
        $qb = $this->createQueryBuilder('a')
            ->join('a.mission', 'm')->addSelect('m')
            ->join('a.hero', 'h')->addSelect('h')
            ->andWhere('h.id IN (:heros)')
            ->andWhere('a.statut IN (:statutsAffectation)')
            ->andWhere('m.statut IN (:statutsMission)')
            ->setParameter('heros', $heroIds)
            ->setParameter('statutsAffectation', [AffectationStatut::AFFECTE, AffectationStatut::SUR_PLACE])
            ->setParameter('statutsMission', MissionStatut::actifs());
        if (null !== $exclure?->getId()) {
            $qb->andWhere('m.id <> :exclue')->setParameter('exclue', $exclure->getId());
        }

        return $qb->getQuery()->getResult();
    }

    /** @return array<int, AffectationMission> indexé par id de héros */
    public function indexerActivesParHero(?Mission $exclure = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->join('a.mission', 'm')->addSelect('m')
            ->join('a.hero', 'h')
            ->andWhere('a.statut IN (:sa)')
            ->andWhere('m.statut IN (:sm)')
            ->setParameter('sa', [AffectationStatut::AFFECTE, AffectationStatut::SUR_PLACE])
            ->setParameter('sm', MissionStatut::actifs());
        if (null !== $exclure?->getId()) {
            $qb->andWhere('m.id <> :ex')->setParameter('ex', $exclure->getId());
        }
        $index = [];
        foreach ($qb->getQuery()->getResult() as $affectation) {
            $index[$affectation->getHero()->getId()] = $affectation;
        }

        return $index;
    }
}
