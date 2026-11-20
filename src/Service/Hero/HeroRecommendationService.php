<?php

namespace App\Service\Hero;

use App\Infirmerie\DisponibiliteMedicale;
use App\Entity\Hero;
use App\Entity\Mission;
use App\Repository\AffectationMissionRepository;
use App\Service\Map\PositionDistanceCalculator;
use App\Service\Map\TacticalGrid;
use App\Service\Mission\EffectiveStatsResolver;
use App\Service\Mission\SynergyResolver;
use Psr\Clock\ClockInterface;

/**
 * Classe les héros candidats pour une mission (affinité 0-100). Simple aide à la décision :
 * le choix final reste à l'opérateur et le déploiement est revalidé par DeploymentValidator.
 *
 * Score =  20 × santé
 *        + 15 × proximité (1 − distance / 50 km)
 *        + 30 × couverture des statistiques requises (ou moyenne des stats / 100 sans exigence)
 *        + 10 × min(1, niveau / niveau recommandé)
 *        + 10 si le rôle du héros manque à l'équipe
 *        + 10 par aptitude requise qu'il est le seul à apporter (max 20)
 *        + min(10, bonus de synergie débloqués avec l'équipe actuelle)
 *        + min(5, bonus actifs positifs / 4)
 *        − 0,1 × écart de combat avec le vilain le plus fort
 *        − fatigue / 10
 * borné à [0, 100]. Un héros indisponible, engagé ailleurs ou sous le seuil de santé est inéligible,
 * de même qu'un héros à l'infirmerie ou en état médical critique (motif affiché).
 */
final class HeroRecommendationService
{
    public function __construct(
        private readonly PositionDistanceCalculator $distances,
        private readonly EffectiveStatsResolver $statsResolver,
        private readonly SynergyResolver $synergyResolver,
        private readonly AffectationMissionRepository $affectations,
        private readonly ClockInterface $clock,
        private readonly ?DisponibiliteMedicale $medical = null,
    ) {
    }

    /**
     * @param list<Hero> $candidats
     * @param list<Hero> $equipe    composition actuelle
     *
     * @return list<HeroRecommendation> triées par éligibilité puis score décroissant
     */
    public function recommander(Mission $mission, array $candidats, array $equipe = []): array
    {
        $engages = $this->affectations->indexerActivesParHero($mission);
        $sejours = $this->medical?->sejoursActifs(array_map(static fn (Hero $h) => (int) $h->getId(), $candidats)) ?? [];
        $recommandations = [];
        foreach ($candidats as $hero) {
            if (\in_array($hero, $equipe, true)) {
                continue;
            }
            $recommandations[] = $this->evaluer($mission, $hero, $equipe, $engages[$hero->getId()] ?? null, $this->medical?->motif($hero, $sejours[$hero->getId()] ?? null, false));
        }

        usort($recommandations, static fn (HeroRecommendation $a, HeroRecommendation $b) => [$b->eligible, $b->score] <=> [$a->eligible, $a->score]);

        return $recommandations;
    }

    /**
     * @param list<Hero> $candidats
     * @param list<Hero> $equipe
     */
    public function meilleure(Mission $mission, array $candidats, array $equipe = []): ?HeroRecommendation
    {
        foreach ($this->recommander($mission, $candidats, $equipe) as $recommandation) {
            if ($recommandation->eligible) {
                return $recommandation;
            }
        }

        return null;
    }

    /** @param list<Hero> $equipe */
    /** @param string|false|null $motifMedical false = à déterminer, null = apte, chaîne = indisponible */
    public function evaluer(Mission $mission, Hero $hero, array $equipe = [], ?object $affectationAilleurs = null, string|false|null $motifMedical = false): HeroRecommendation
    {
        $now = $this->clock->now();
        $raisons = [];
        $distance = round($this->distances->heroMission($hero, $mission), 1);
        if (false === $motifMedical) {
            $motifMedical = $this->medical?->motif($hero);
        }

        $motif = match (true) {
            null !== $motifMedical => $motifMedical,
            null !== $affectationAilleurs => 'Engagé sur une autre mission',
            !$hero->isDisponible() => $hero->getStatut()->label(),
            $hero->getSantePourcentage() < $mission->getSeuilSanteMinimal() => 'Santé insuffisante',
            default => null,
        };

        $score = 20 * $hero->getSantePourcentage() / 100;
        $score += 15 * max(0.0, 1 - $distance / TacticalGrid::DISTANCE_MAX_UTILE);

        $stats = $this->statsResolver->statsEffectives($hero, $now);
        $requises = $mission->getStatsRequises();
        if ([] !== $requises) {
            $couverture = 0.0;
            foreach ($requises as $stat => $requis) {
                $couverture += min(1.0, $stats[$stat] / $requis);
                if ($stats[$stat] >= $requis) {
                    $raisons[] = sprintf('Atteint l\'exigence %s', mb_strtolower($stat));
                }
            }
            $score += 30 * $couverture / \count($requises);
        } else {
            $score += 30 * array_sum($stats) / 500;
        }

        $recommande = $mission->getDifficulte()?->niveauRecommande() ?? 20;
        $score += 10 * min(1.0, (int) $hero->getNiveau() / $recommande);

        $rolesEquipe = array_map(static fn (Hero $h) => $h->getRole(), $equipe);
        if ([] !== $equipe && !\in_array($hero->getRole(), $rolesEquipe, true)) {
            $score += 10;
            $raisons[] = sprintf('Apporte le rôle %s', $hero->getRole()->label());
        }

        $apport = 0;
        foreach ($mission->getAptitudesRequises() as $aptitude) {
            if (!$hero->getAptitudes()->contains($aptitude)) {
                continue;
            }
            $dejaCouverte = false;
            foreach ($equipe as $membre) {
                if ($membre->getAptitudes()->contains($aptitude)) {
                    $dejaCouverte = true;
                    break;
                }
            }
            if (!$dejaCouverte) {
                $apport += 10;
                $raisons[] = sprintf('Apporte %s', $aptitude->getNom());
            }
        }
        $score += min(20, $apport);

        if ([] !== $equipe) {
            $avant = $this->totalSynergie($this->synergyResolver->synergiesActives($equipe, $mission));
            $apres = $this->totalSynergie($this->synergyResolver->synergiesActives([...$equipe, $hero], $mission));
            if ($apres > $avant) {
                $score += min(10, $apres - $avant);
                $raisons[] = sprintf('Synergie +%d', $apres - $avant);
            }
        }

        $bonusPositifs = array_sum(array_filter($this->statsResolver->bonusParStatistique($hero, $now), static fn (int $v) => $v > 0));
        $score += min(5, $bonusPositifs / 4);

        $combatVilain = 0;
        foreach ($mission->getVillains() as $villain) {
            $combatVilain = max($combatVilain, $villain->getCombat());
        }
        $score -= 0.1 * max(0, $combatVilain - $stats['COMBAT']);
        $score -= $hero->getFatigue() / 10;

        $score = (int) round(max(0, min(100, $score)));

        return new HeroRecommendation($hero, $score, null === $motif, $distance, $raisons, $motif);
    }

    /** @param list<\App\Entity\Synergie> $synergies */
    private function totalSynergie(array $synergies): int
    {
        return array_sum(array_map(static fn ($s) => $s->getValeurBonus(), $synergies));
    }
}
