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

    // ------------------------------------------------------------------
    // Ajouts (additifs) — filtres du traitement opérateur.
    // ------------------------------------------------------------------

    /**
     * Recherche multi-critères. Le statut et la catégorie détaillés sont filtrés après
     * lecture car ils retombent sur les colonnes historiques pour les anciennes requêtes.
     *
     * @return list<RequeteCitoyenne>
     */
    public function rechercher(
        ?\App\Enum\NiveauUrgence $urgence = null,
        ?\App\Enum\CategorieIncident $categorie = null,
        ?string $secteur = null,
        ?\App\Enum\StatutTraitement $statut = null,
        ?RequeteCategorie $categorieHistorique = null,
        bool $urgentesSeulement = false,
        string $tri = 'urgence',
    ): array {
        $requetes = $this->filtrer($categorieHistorique, $urgentesSeulement, $tri);

        return array_values(array_filter($requetes, static fn (RequeteCitoyenne $r) => (null === $urgence || $r->getNiveauUrgence() === $urgence)
            && (null === $categorie || $r->getCategorieIncident() === $categorie)
            && (null === $statut || $r->getStatutTraitement() === $statut)
            && (null === $secteur || '' === $secteur || $r->getSecteur() === $secteur)));
    }

    /** @return list<string> secteurs connus, triés */
    public function secteursConnus(): array
    {
        $rows = $this->createQueryBuilder('r')->select('DISTINCT r.secteur AS s')->andWhere('r.secteur IS NOT NULL')->orderBy('r.secteur')->getQuery()->getArrayResult();

        return array_values(array_map(static fn (array $row) => (string) $row['s'], $rows));
    }

    /** Requêtes publiques encore nouvelles (badge de notification des opérateurs missions). */
    public function compterNouvellesPubliques(): int
    {
        return (int) $this->createQueryBuilder('r')->select('COUNT(r.id)')
            ->andWhere('r.soumissionPublique = true')
            ->andWhere('r.statutTraitement = :s')->setParameter('s', \App\Enum\StatutTraitement::NOUVELLE)
            ->getQuery()->getSingleScalarResult();
    }
}
