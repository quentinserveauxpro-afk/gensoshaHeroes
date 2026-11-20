<?php

namespace App\Repository;

use App\Entity\Hero;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\DBAL\LockMode;
use App\Enum\HeroRole;

/**
 * @extends ServiceEntityRepository<Hero>
 */
class HeroRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Hero::class);
    }

//    /**
//     * @return Hero[] Returns an array of Hero objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('h')
//            ->andWhere('h.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('h.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Hero
//    {
//        return $this->createQueryBuilder('h')
//            ->andWhere('h.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }

    /**
     * Charge les héros en posant un verrou d'écriture (SELECT … FOR UPDATE).
     * À appeler uniquement à l'intérieur d'une transaction.
     *
     * @param list<int> $ids
     *
     * @return list<Hero>
     */
    public function findPourMiseAJour(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        return $this->createQueryBuilder('h')
            ->andWhere('h.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('h.id', 'ASC')
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getResult();
    }

    /**
     * @param list<int> $ids
     *
     * @return list<Hero> dans l'ordre des identifiants fournis
     */
    public function findParIdsOrdonnes(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }
        $heros = [];
        foreach ($this->findBy(['id' => $ids]) as $hero) {
            $heros[$hero->getId()] = $hero;
        }

        return array_values(array_filter(array_map(static fn (int $id) => $heros[$id] ?? null, $ids)));
    }

    /** @return Paginator<Hero> */
    public function paginer(int $page, int $parPage, ?string $recherche = null, ?HeroRole $role = null, string $tri = 'niveau'): Paginator
    {
        $qb = $this->createQueryBuilder('h');
        if (null !== $recherche && '' !== trim($recherche)) {
            $qb->andWhere('LOWER(h.nom) LIKE :q OR LOWER(h.alias) LIKE :q OR LOWER(h.specialite) LIKE :q')
                ->setParameter('q', '%'.mb_strtolower(trim($recherche)).'%');
        }
        if (null !== $role) {
            $qb->andWhere('h.role = :role')->setParameter('role', $role);
        }
        match ($tri) {
            'nom' => $qb->orderBy('h.nom', 'ASC'),
            'sante' => $qb->orderBy('h.pointsDeVie', 'DESC'),
            default => $qb->orderBy('h.niveau', 'DESC')->addOrderBy('h.nom', 'ASC'),
        };
        $qb->setFirstResult(max(0, $page - 1) * $parPage)->setMaxResults($parPage);

        return new Paginator($qb->getQuery());
    }

    /** @return array<string, int> nombre de héros par rôle (clé = valeur de HeroRole) */
    public function compterParRole(): array
    {
        $rows = $this->createQueryBuilder('h')
            ->select('h.role AS role, COUNT(h.id) AS total')
            ->groupBy('h.role')
            ->getQuery()->getArrayResult();
        $result = [];
        foreach ($rows as $row) {
            $role = $row['role'] instanceof HeroRole ? $row['role']->value : (string) $row['role'];
            $result[$role] = (int) $row['total'];
        }

        return $result;
    }

    /** @return array<string, int> nombre de héros par statut */
    public function compterParStatut(): array
    {
        $rows = $this->createQueryBuilder('h')
            ->select('h.statut AS statut, COUNT(h.id) AS total')
            ->groupBy('h.statut')
            ->getQuery()->getArrayResult();
        $result = [];
        foreach ($rows as $row) {
            $statut = $row['statut'] instanceof \BackedEnum ? $row['statut']->value : (string) $row['statut'];
            $result[$statut] = (int) $row['total'];
        }

        return $result;
    }

    /** @return list<Hero> */
    public function findReserve(): array
    {
        return $this->createQueryBuilder('h')
            ->leftJoin('h.aptitudes', 'apt')->addSelect('apt')
            ->orderBy('h.niveau', 'DESC')
            ->getQuery()->getResult();
    }

    /** @return list<Hero> héros possédant l'aptitude (code) */
    public function findAvecAptitude(string $code): array
    {
        return $this->createQueryBuilder('h')->join('h.aptitudes', 'a')
            ->andWhere('a.code = :code')->setParameter('code', $code)
            ->getQuery()->getResult();
    }
}
