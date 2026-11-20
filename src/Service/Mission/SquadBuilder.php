<?php

namespace App\Service\Mission;

use App\Entity\Hero;
use App\Entity\Mission;
use App\Enum\HeroRole;
use App\Infirmerie\DisponibiliteMedicale;
use App\Repository\HeroRepository;
use App\Service\Hero\HeroRecommendation;
use App\Service\Hero\HeroRecommendationService;
use App\Service\Map\PositionDistanceCalculator;
use App\Service\Map\TacticalGrid;

/**
 * Assemble l'état du Squad Builder à partir de paramètres de requête non fiables :
 * composition, nombre d'emplacements, filtres de la réserve, pronostic et validation.
 */
final class SquadBuilder
{
    public const FILTRES = ['tous', 'disponibles', 'base', 'proximite'];
    public const TAILLE_ABSOLUE_MAX = 12;

    public function __construct(
        private readonly HeroRepository $heroes,
        private readonly MissionSuccessCalculator $calculator,
        private readonly DeploymentValidator $validator,
        private readonly HeroRecommendationService $recommandations,
        private readonly PositionDistanceCalculator $distances,
        private readonly ?DisponibiliteMedicale $medical = null,
    ) {
    }

    /** @return list<int> identifiants uniques, entiers positifs, dans l'ordre fourni */
    public static function parserEquipe(?string $brut): array
    {
        if (null === $brut || '' === trim($brut)) {
            return [];
        }
        $ids = [];
        foreach (explode(',', $brut) as $morceau) {
            $id = filter_var(trim($morceau), \FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (false !== $id && !\in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return \array_slice($ids, 0, self::TAILLE_ABSOLUE_MAX);
    }

    /**
     * @param list<int>          $ids
     * @param array<string, mixed> $filtres filtre, role, niveau, sante
     *
     * @return array<string, mixed>
     */
    public function construire(Mission $mission, array $ids, ?int $taille, array $filtres): array
    {
        $equipe = $this->heroes->findParIdsOrdonnes($ids);
        // Un héros à l'infirmerie n'est ni sélectionnable ni comptabilisé (synergies, pronostic).
        $exclus = [];
        if (null !== $this->medical) {
            $sejours = $this->medical->sejoursActifs(array_map(static fn (Hero $h) => (int) $h->getId(), $equipe));
            foreach ($equipe as $hero) {
                if (isset($sejours[$hero->getId()])) {
                    $exclus[] = ['hero' => $hero, 'motif' => $this->medical->motif($hero, $sejours[$hero->getId()], false)];
                }
            }
            $equipe = array_values(array_filter($equipe, static fn (Hero $h) => !isset($sejours[$h->getId()])));
        }
        $taille = max($mission->getTailleEquipeMinimale(), min($mission->getTailleEquipeMaximale(), $taille ?? max(\count($equipe), $mission->getTailleEquipeMinimale())));
        $taille = max($taille, \count($equipe));

        $tous = $this->heroes->findReserve();
        $recommandations = $this->recommandations->recommander($mission, $tous, $equipe);
        $compteurs = $this->compter($recommandations);
        $reserve = array_values(array_filter($recommandations, fn (HeroRecommendation $r) => $this->correspond($r, $filtres)));

        $membres = [];
        foreach ($equipe as $hero) {
            $membres[] = [
                'hero' => $hero,
                'distance' => $this->distances->heroMissionArrondie($hero, $mission),
                'evaluation' => $this->recommandations->evaluer($mission, $hero, array_values(array_filter($equipe, static fn (Hero $h) => $h !== $hero))),
            ];
        }

        return [
            'equipe' => $equipe,
            'membres' => $membres,
            'ids' => array_map(static fn (Hero $h) => (int) $h->getId(), $equipe),
            'taille' => $taille,
            'pronostic' => $this->calculator->calculer($mission, $equipe),
            'rapport' => $this->validator->valider($mission, $equipe),
            'reserve' => $reserve,
            'compteurs' => $compteurs,
            'exclus' => $exclus,
            'suggestion' => \count($equipe) < $taille ? $this->recommandations->meilleure($mission, $tous, $equipe) : null,
        ];
    }

    /** @param array<string, mixed> $filtres */
    private function correspond(HeroRecommendation $r, array $filtres): bool
    {
        $hero = $r->hero;

        $ok = match ($filtres['filtre'] ?? 'tous') {
            'disponibles' => $r->eligible,
            'base' => $this->distances->isALaBase($hero),
            'proximite' => $r->distance < TacticalGrid::RAYON_PROXIMITE,
            default => true,
        };
        if (!$ok) {
            return false;
        }
        if (($filtres['role'] ?? null) instanceof HeroRole && $hero->getRole() !== $filtres['role']) {
            return false;
        }
        if (null !== ($filtres['niveau'] ?? null) && (int) $hero->getNiveau() < $filtres['niveau']) {
            return false;
        }

        return null === ($filtres['sante'] ?? null) || $hero->getSantePourcentage() >= $filtres['sante'];
    }

    /**
     * @param list<HeroRecommendation> $recommandations
     *
     * @return array<string, int>
     */
    private function compter(array $recommandations): array
    {
        $c = ['tous' => \count($recommandations), 'disponibles' => 0, 'base' => 0, 'proximite' => 0];
        foreach ($recommandations as $r) {
            $c['disponibles'] += (int) $r->eligible;
            $c['base'] += (int) $this->distances->isALaBase($r->hero);
            $c['proximite'] += (int) ($r->distance < TacticalGrid::RAYON_PROXIMITE);
        }

        return $c;
    }
}
