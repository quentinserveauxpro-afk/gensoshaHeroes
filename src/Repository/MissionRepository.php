<?php

namespace App\Repository;

use App\Entity\Mission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\DBAL\LockMode;
use App\Enum\NiveauUrgence;
use App\Enum\MissionStatut;
use App\Enum\Difficulte;

/**
 * @extends ServiceEntityRepository<Mission>
 */
class MissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Mission::class);
    }

//    /**
//     * @return Mission[] Returns an array of Mission objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('m')
//            ->andWhere('m.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('m.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Mission
//    {
//        return $this->createQueryBuilder('m')
//            ->andWhere('m.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }

    /**
     * Missions filtrées pour le centre tactique et la liste des missions.
     *
     * @param list<MissionStatut>|null $statuts null = toutes
     *
     * @return list<Mission>
     */
    public function filtrer(?array $statuts = null, ?Difficulte $difficulte = null, ?NiveauUrgence $urgence = null, ?int $limite = null): array
    {
        $qb = $this->createQueryBuilder('m')
            ->orderBy('m.timer', 'ASC');
        if (null !== $statuts && [] !== $statuts) {
            $qb->andWhere('m.statut IN (:statuts)')->setParameter('statuts', $statuts);
        }
        if (null !== $difficulte) {
            $qb->andWhere('m.niveauDeDifficulte = :diff')->setParameter('diff', $difficulte->value);
        }
        if (null !== $urgence) {
            $qb->andWhere('m.niveauUrgence = :urg')->setParameter('urg', $urgence);
        }
        if (null !== $limite) {
            $qb->setMaxResults($limite);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return Paginator<Mission> */
    public function paginer(int $page, int $parPage, ?MissionStatut $statut = null): Paginator
    {
        $qb = $this->createQueryBuilder('m')->orderBy('m.createdAt', 'DESC');
        if (null !== $statut) {
            $qb->andWhere('m.statut = :s')->setParameter('s', $statut);
        }
        $qb->setFirstResult(max(0, $page - 1) * $parPage)->setMaxResults($parPage);

        return new Paginator($qb->getQuery());
    }

    /** Recharge la mission avec un verrou d'écriture (dans une transaction). */
    public function findPourMiseAJour(int $id): ?Mission
    {
        return $this->getEntityManager()->find(Mission::class, $id, LockMode::PESSIMISTIC_WRITE);
    }

    /** @return array<string, int> */
    public function compterParStatut(): array
    {
        $rows = $this->createQueryBuilder('m')
            ->select('m.statut AS statut, COUNT(m.id) AS total')
            ->groupBy('m.statut')
            ->getQuery()->getArrayResult();
        $result = [];
        foreach ($rows as $row) {
            $statut = $row['statut'] instanceof \BackedEnum ? $row['statut']->value : (string) $row['statut'];
            $result[$statut] = (int) $row['total'];
        }

        return $result;
    }
}
