<?php

namespace App\Twig;

use App\Entity\Hero;
use App\Entity\Mission;
use App\Repository\AlerteRepository;
use App\Service\Hero\HeroProgressionService;
use App\Service\Map\PositionDistanceCalculator;
use App\Service\Mission\MissionTimer;
use App\Service\Mission\TimerSnapshot;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

/** Fonctions Twig minces : elles délèguent aux services métier, aucun calcul dans les templates. */
final class AppExtension
{
    public function __construct(
        private readonly MissionTimer $timer,
        private readonly PositionDistanceCalculator $distances,
        private readonly HeroProgressionService $progression,
        private readonly AlerteRepository $alertes,
    ) {
    }

    #[AsTwigFunction('mission_timer')]
    public function missionTimer(Mission $mission): TimerSnapshot
    {
        return $this->timer->snapshot($mission);
    }

    #[AsTwigFunction('distance_km')]
    public function distanceKm(Hero $hero, Mission $mission): float
    {
        return $this->distances->heroMissionArrondie($hero, $mission);
    }

    #[AsTwigFunction('xp_seuil')]
    public function xpSeuil(Hero $hero): int
    {
        return $this->progression->seuil((int) $hero->getNiveau());
    }

    #[AsTwigFunction('xp_pourcentage')]
    public function xpPourcentage(Hero $hero): int
    {
        return $this->progression->pourcentageNiveau($hero);
    }

    /** @return list<\App\Entity\Alerte> */
    #[AsTwigFunction('alertes_actives')]
    public function alertesActives(int $limite = 5): array
    {
        return $this->alertes->findActives($limite);
    }

    /** Ton d'une jauge selon un pourcentage. */
    #[AsTwigFunction('jauge_tone')]
    public function jaugeTone(int|float $pourcentage): string
    {
        return match (true) {
            $pourcentage < 40 => 'danger',
            $pourcentage < 70 => 'warn',
            default => 'ok',
        };
    }

    #[AsTwigFilter('tronquer')]
    public function tronquer(?string $texte, int $longueur): string
    {
        $texte = (string) $texte;

        return mb_strlen($texte) > $longueur ? rtrim(mb_substr($texte, 0, $longueur - 1)).'…' : $texte;
    }
}
