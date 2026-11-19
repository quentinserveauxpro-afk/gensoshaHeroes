<?php

namespace App\Repository;

use App\Entity\RequeteCitoyenne;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Enum\NiveauUrgence;
use App\Enum\RequeteStatut;
use App\Enum\RequeteCategorie;

/**
 * @extends ServiceEntityRepository<RequeteCitoyenne>
 */
class RequeteCitoyenneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RequeteCitoyenne::class);
    }

    /** @return list<RequeteCitoyenne> */
    public function filtrer(?RequeteCategorie $categorie = null, bool $urgentesSeulement = false, string $tri = 'urgence'): array
    {
        $qb = $this->createQueryBuilder('r')->leftJoin('r.mission', 'm')->addSelect('m');
        if (null !== $categorie) {
            $qb->andWhere('r.categorie = :c')->setParameter('c', $categorie);
        }
        if ($urgentesSeulement) {
            $qb->andWhere('r.niveauUrgence IN (:u)')->setParameter('u', [NiveauUrgence::HAUTE, NiveauUrgence::CRITIQUE]);
        }
        $requetes = $qb->orderBy('r.dateCreation', 'DESC')->getQuery()->getResult();
        if ('urgence' === $tri) {
            usort($requetes, static fn (RequeteCitoyenne $a, RequeteCitoyenne $b) => [$b->getStatut()->isOuverte(), $b->getNiveauUrgence()->poids(), $b->getDateCreation()]
                <=> [$a->getStatut()->isOuverte(), $a->getNiveauUrgence()->poids(), $a->getDateCreation()]);
        }

        return $requetes;
    }

    /** @return array<string, int> nombre par catégorie */
    public function compterParCategorie(): array
    {
        $result = [];
        foreach ($this->createQueryBuilder('r')->select('r.categorie AS c, COUNT(r.id) AS n')->groupBy('r.categorie')->getQuery()->getArrayResult() as $row) {
            $result[$row['c'] instanceof \BackedEnum ? $row['c']->value : (string) $row['c']] = (int) $row['n'];
        }

        return $result;
    }

    public function compterUrgentesNonTraitees(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.niveauUrgence IN (:u)')
            ->andWhere('r.statut IN (:s)')
            ->setParameter('u', [NiveauUrgence::HAUTE, NiveauUrgence::CRITIQUE])
            ->setParameter('s', [RequeteStatut::NOUVELLE, RequeteStatut::EN_QUALIFICATION])
            ->getQuery()->getSingleScalarResult();
    }

    public function compterDepuis(\DateTimeImmutable $depuis): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.dateCreation >= :d')->setParameter('d', $depuis)
            ->getQuery()->getSingleScalarResult();
    }
}
