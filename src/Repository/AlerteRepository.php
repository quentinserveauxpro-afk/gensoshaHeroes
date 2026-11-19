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
}
