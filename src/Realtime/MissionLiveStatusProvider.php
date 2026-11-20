<?php

namespace App\Realtime;

use App\Entity\Alerte;
use App\Entity\Hero;
use App\Entity\Mission;
use App\Entity\MissionSimulationState;
use App\Repository\AlerteRepository;
use App\Repository\MissionRepository;
use App\Repository\MissionSimulationStateRepository;
use App\Service\Mission\MissionTimer;
use App\Simulation\HeroHealthCalculator;
use App\Simulation\MissionSimulationService;
use Psr\Clock\ClockInterface;

/**
 * État courant des missions affichées (page Missions, centre tactique), pour le
 * rafraîchissement automatique.
 *
 * Lecture seule : l'état est calculé par le moteur (app:missions:update) ; ce service le lit
 * sans rien simuler ni écrire. Trois requêtes au total, quel que soit le nombre de missions :
 * missions + objectifs + affectations + héros + position, états de simulation, alertes.
 * Aucune description ni image : seules les valeurs qui évoluent.
 */
final class MissionLiveStatusProvider
{
    /** Nombre maximal de missions suivies par requête (une page de liste en compte 10). */
    public const MAX_MISSIONS = 50;

    public function __construct(
        private readonly MissionRepository $missions,
        private readonly MissionSimulationStateRepository $etats,
        private readonly AlerteRepository $alertes,
        private readonly MissionSimulationService $simulation,
        private readonly MissionTimer $timer,
        private readonly HeroHealthCalculator $sante,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Identifiants transmis par le navigateur : uniquement des entiers positifs, dédoublonnés,
     * plafonnés à MAX_MISSIONS. Tout le reste est ignoré.
     *
     * @return list<int>
     */
    public static function identifiants(string $brut): array
    {
        $ids = [];
        foreach (explode(',', $brut) as $morceau) {
            $morceau = trim($morceau);
            if ('' !== $morceau && ctype_digit($morceau) && \strlen($morceau) <= 10 && (int) $morceau > 0) {
                $ids[(int) $morceau] = true;
            }
        }

        return \array_slice(array_keys($ids), 0, self::MAX_MISSIONS);
    }

    /**
     * @param list<int> $ids
     *
     * @return array{serverTime: string, missions: list<array<string, mixed>>}
     */
    public function pourIdentifiants(array $ids): array
    {
        return $this->pourMissions($this->missions->chargerPourSuiviDirect($ids));
    }

    /**
     * @param list<Mission> $missions missions déjà chargées (avec leurs relations de préférence)
     *
     * @return array{serverTime: string, missions: list<array<string, mixed>>}
     */
    public function pourMissions(array $missions): array
    {
        $now = $this->clock->now();
        $ids = array_values(array_filter(array_map(static fn (Mission $m) => $m->getId(), $missions)));
        $etats = $this->etats->indexerParMission($ids);
        $alertes = $this->alertes->indexerActivesParMission($ids);

        return [
            'serverTime' => $now->format(\DATE_ATOM),
            'missions' => array_map(fn (Mission $m) => $this->mission($m, $etats[$m->getId()] ?? null, $alertes[$m->getId()] ?? []), $missions),
        ];
    }

    /**
     * @param list<Alerte> $alertes
     *
     * @return array<string, mixed>
     */
    private function mission(Mission $mission, ?MissionSimulationState $etat, array $alertes): array
    {
        $statut = $mission->getStatut();
        $coordonnees = $mission->getCoordonnees();

        return [
            'id' => $mission->getId(),
            'status' => $statut->value,
            'statusLabel' => $statut->label(),
            'closed' => $statut->isClosed(),
            'phase' => $this->simulation->phase($mission, $etat),
            'urgency' => $mission->getNiveauUrgence()->value,
            'urgencyLabel' => $mission->getNiveauUrgence()->label(),
            'critical' => $mission->isCritique(),
            'progress' => self::pourcentage($this->simulation->progressionObligatoire($mission, $etat)),
            'successRate' => self::pourcentage((float) $mission->getTauxDeReussite()),
            'deadline' => $mission->getDateFinPrevue()?->format(\DATE_ATOM),
            'remainingSeconds' => $statut->isClosed() ? 0 : $this->timer->secondesRestantes($mission),
            'updatedAt' => ($etat?->getUpdatedAt() ?? $mission->getDateFinReelle() ?? $mission->getDateDebut())?->format(\DATE_ATOM),
            'position' => $coordonnees?->toArray(),
            'objectives' => array_values(array_map(static fn ($o) => [
                'id' => $o->getId(),
                'progress' => $o->isTermine() ? 100 : self::pourcentage(null !== $etat && null !== $o->getId()
                    ? $etat->getProgressionPrecise($o->getId(), $o->getProgression())
                    : (float) $o->getProgression()),
                'completed' => $o->isTermine(),
            ], $mission->getObjectifs()->toArray())),
            'heroes' => array_map(fn (Hero $h) => [
                'id' => $h->getId(),
                'name' => $h->getNom(),
                'health' => max(0, (int) $h->getPointsDeVie()),
                'maxHealth' => max(0, (int) $h->getPointsDeVieMaximum()),
                'healthPercent' => self::pourcentage((float) $h->getSantePourcentage()),
                'fatigue' => self::pourcentage((float) $h->getFatigue()),
                'critical' => $this->sante->estCritique($h),
            ], $mission->getHerosAffectes()),
            'alerts' => array_map(static fn (Alerte $a) => [
                'id' => $a->getId(),
                'title' => $a->getTitre(),
                'level' => $a->getNiveau()->value,
                'tone' => $a->getNiveau()->tone(),
            ], $alertes),
        ];
    }

    /** Entier dans [0, 100] ; 0 pour une valeur non finie. */
    public static function pourcentage(float $valeur): int
    {
        return is_finite($valeur) ? (int) max(0, min(100, floor($valeur))) : 0;
    }
}
