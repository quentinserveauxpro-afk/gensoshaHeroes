<?php

namespace App\Repository;

use App\Entity\Alerte;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Alerte>
 */
class AlerteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Alerte::class);
    }

    /** @return list<Alerte> */
    public function findActives(int $limite = 10): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.acquittee = false')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()->getResult();
    }

    /** Une alerte non acquittée dont le code commence par ce préfixe existe-t-elle ? (anti-doublon) */
    public function existeNonAcquittee(string $prefixeCode): bool
    {
        return (int) $this->createQueryBuilder('a')->select('COUNT(a.id)')
            ->andWhere('a.acquittee = false')
            ->andWhere('a.code LIKE :p')->setParameter('p', addcslashes($prefixeCode, '%_').'%')
            ->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Alertes non acquittées des missions suivies, en une requête, indexées par mission.
     *
     * @param list<int> $missionIds
     *
     * @return array<int, list<Alerte>>
     */
    public function indexerActivesParMission(array $missionIds, int $parMission = 3): array
    {
        if ([] === $missionIds) {
            return [];
        }
        $index = [];
        $resultats = $this->createQueryBuilder('a')
            ->andWhere('a.acquittee = false')
            ->andWhere('IDENTITY(a.mission) IN (:ids)')->setParameter('ids', $missionIds)
            ->orderBy('a.createdAt', 'DESC')->addOrderBy('a.id', 'DESC')
            ->getQuery()->getResult();
        foreach ($resultats as $alerte) {
            $id = (int) $alerte->getMission()?->getId();
            if (\count($index[$id] ?? []) < $parMission) {
                $index[$id][] = $alerte;
            }
        }

        return $index;
    }
}
