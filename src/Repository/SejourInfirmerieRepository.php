<?php

namespace App\Repository;

use App\Entity\Hero;
use App\Entity\SejourInfirmerie;
use App\Enum\MotifInfirmerie;
use App\Enum\StatutSejour;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SejourInfirmerie> */
class SejourInfirmerieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SejourInfirmerie::class);
    }

    public function findActifPourHero(Hero $hero): ?SejourInfirmerie
    {
        return $this->findOneBy(['cleActive' => $hero->getId()]);
    }

    /** @return list<SejourInfirmerie> séjours actifs, héros chargés en une requête */
    public function findActifs(): array
    {
        return $this->createQueryBuilder('s')->join('s.hero', 'h')->addSelect('h')
            ->leftJoin('s.mission', 'm')->addSelect('m')
            ->andWhere('s.cleActive IS NOT NULL')
            ->orderBy('s.admisLe', 'DESC')
            ->getQuery()->getResult();
    }

    /**
     * Séjours actifs indexés par identifiant de héros (une requête pour toute une liste).
     *
     * @param list<int>|null $heroIds null = tous
     *
     * @return array<int, SejourInfirmerie>
     */
    public function indexerActifsParHero(?array $heroIds = null): array
    {
        $qb = $this->createQueryBuilder('s')->andWhere('s.cleActive IS NOT NULL');
        if (null !== $heroIds) {
            if ([] === $heroIds) {
                return [];
            }
            $qb->andWhere('s.cleActive IN (:ids)')->setParameter('ids', $heroIds);
        }
        $index = [];
        foreach ($qb->getQuery()->getResult() as $sejour) {
            $index[(int) $sejour->getCleActive()] = $sejour;
        }

        return $index;
    }

    public function compterActifs(): int
    {
        return (int) $this->createQueryBuilder('s')->select('COUNT(s.id)')->andWhere('s.cleActive IS NOT NULL')->getQuery()->getSingleScalarResult();
    }

    /**
     * @param array{statut?: ?StatutSejour, motif?: ?MotifInfirmerie, rang?: ?string, depuis?: ?\DateTimeImmutable} $filtres
     *
     * @return list<SejourInfirmerie>
     */
    public function rechercher(bool $actifs, array $filtres = [], int $limite = 100): array
    {
        $qb = $this->createQueryBuilder('s')->join('s.hero', 'h')->addSelect('h')
            ->leftJoin('s.mission', 'm')->addSelect('m')
            ->andWhere($actifs ? 's.cleActive IS NOT NULL' : 's.cleActive IS NULL')
            ->orderBy($actifs ? 's.admisLe' : 's.sortiLe', 'DESC')
            ->setMaxResults($limite);
        if (null !== ($filtres['statut'] ?? null)) {
            $qb->andWhere('s.statut = :statut')->setParameter('statut', $filtres['statut']);
        }
        if (null !== ($filtres['motif'] ?? null)) {
            $qb->andWhere('s.motif = :motif')->setParameter('motif', $filtres['motif']);
        }
        if (null !== ($filtres['rang'] ?? null) && '' !== $filtres['rang']) {
            $qb->andWhere('h.rang = :rang')->setParameter('rang', $filtres['rang']);
        }
        if (null !== ($filtres['depuis'] ?? null)) {
            $qb->andWhere('s.admisLe >= :depuis')->setParameter('depuis', $filtres['depuis']);
        }

        return $qb->getQuery()->getResult();
    }

    /** Durée moyenne de traitement (secondes) des séjours terminés ou rétablis. */
    public function dureeMoyenneTraitement(int $echantillon = 50): ?int
    {
        $sejours = $this->createQueryBuilder('s')
            ->andWhere('s.debutTraitementLe IS NOT NULL')
            ->andWhere('s.retabliLe IS NOT NULL')
            ->orderBy('s.retabliLe', 'DESC')->setMaxResults($echantillon)
            ->getQuery()->getResult();
        $durees = array_filter(array_map(static fn (SejourInfirmerie $s) => $s->getDureeTraitement(), $sejours), static fn (?int $d) => null !== $d);

        return [] === $durees ? null : (int) round(array_sum($durees) / \count($durees));
    }

    /** @return list<string> rangs présents parmi les héros ayant séjourné */
    public function rangs(): array
    {
        $rows = $this->createQueryBuilder('s')->join('s.hero', 'h')->select('DISTINCT h.rang AS r')->orderBy('h.rang')->getQuery()->getArrayResult();

        return array_values(array_filter(array_map(static fn (array $r) => (string) $r['r'], $rows)));
    }
}
