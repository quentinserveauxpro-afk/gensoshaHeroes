<?php

namespace App\Repository;

use App\Entity\Hero;
use App\Entity\HeroMovement;
use App\Entity\Mission;
use App\Enum\MouvementStatut;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<HeroMovement> */
class HeroMovementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HeroMovement::class);
    }

    /** @return list<HeroMovement> déplacements non terminés (avec héros et mission) */
    public function findEnCours(): array
    {
        return $this->createQueryBuilder('mv')
            ->join('mv.hero', 'h')->addSelect('h')
            ->join('mv.mission', 'm')->addSelect('m')
            ->andWhere('mv.statut IN (:s)')
            ->setParameter('s', [MouvementStatut::EN_ATTENTE, MouvementStatut::EN_DEPLACEMENT])
            ->orderBy('mv.id', 'ASC')
            ->getQuery()->getResult();
    }

    /** @return list<HeroMovement> */
    public function findPourMission(Mission $mission): array
    {
        return $this->createQueryBuilder('mv')
            ->join('mv.hero', 'h')->addSelect('h')
            ->andWhere('mv.mission = :m')->setParameter('m', $mission)
            ->orderBy('mv.id', 'ASC')
            ->getQuery()->getResult();
    }

    public function findDernierPourHeroEtMission(Hero $hero, Mission $mission): ?HeroMovement
    {
        return $this->createQueryBuilder('mv')
            ->andWhere('mv.hero = :h')->setParameter('h', $hero)
            ->andWhere('mv.mission = :m')->setParameter('m', $mission)
            ->orderBy('mv.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
    }

    /**
     * Dernier déplacement de chaque héros donné, pour l'affichage (un seul aller-retour SQL).
     *
     * @param list<int> $heroIds
     *
     * @return array<int, HeroMovement> indexé par id de héros
     */
    public function indexerDerniersParHero(array $heroIds): array
    {
        if ([] === $heroIds) {
            return [];
        }
        $index = [];
        $mouvements = $this->createQueryBuilder('mv')
            ->join('mv.mission', 'm')->addSelect('m')
            ->andWhere('IDENTITY(mv.hero) IN (:ids)')->setParameter('ids', $heroIds)
            ->orderBy('mv.id', 'ASC')
            ->getQuery()->getResult();
        foreach ($mouvements as $mouvement) {
            $index[$mouvement->getHero()->getId()] = $mouvement;
        }

        return $index;
    }
}
