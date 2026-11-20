<?php

namespace App\Realtime;

use App\Entity\Hero;
use App\Entity\HeroMovement;
use App\Entity\Mission;
use App\Entity\MissionSimulationState;
use App\Enum\MissionStatut;
use App\Geo\Coordonnees;
use App\Entity\SejourInfirmerie;
use App\Geo\DistanceCalculator;
use App\Infirmerie\InfirmerieEtatProvider;
use App\Repository\SejourInfirmerieRepository;
use App\Movement\HeroMovementService;
use App\Repository\AlerteRepository;
use App\Repository\HeroMovementRepository;
use App\Repository\HeroRepository;
use App\Repository\MissionRepository;
use App\Repository\MissionSimulationStateRepository;
use App\Simulation\HeroHealthCalculator;
use App\Simulation\MissionSimulationService;
use App\Simulation\VillainThreatCalculator;
use Psr\Clock\ClockInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Instantané de la situation tactique, calculé côté serveur et diffusé aux navigateurs
 * (flux SSE ou requête JSON périodique). Une seule requête renvoie tous les héros et
 * toutes les missions actives : jamais une requête par héros.
 *
 * Les positions des héros en déplacement sont calculées à l'instant de la requête
 * (fonction pure du temps, voir HeroMovementService::etatA) ; l'arrivée, elle, n'est
 * actée que par le moteur (app:missions:update).
 */
final class EtatTactiqueProvider
{
    /** Distance (≈ 600 m) des marqueurs de vilains autour de leur mission. */
    private const DECALAGE_VILAIN = 0.006;

    public function __construct(
        private readonly MissionRepository $missions,
        private readonly HeroRepository $heroes,
        private readonly HeroMovementRepository $mouvements,
        private readonly MissionSimulationStateRepository $etats,
        private readonly AlerteRepository $alertes,
        private readonly HeroMovementService $deplacements,
        private readonly DistanceCalculator $distances,
        private readonly HeroHealthCalculator $sante,
        private readonly VillainThreatCalculator $menaces,
        private readonly MissionSimulationService $simulation,
        private readonly UrlGeneratorInterface $urls,
        private readonly ClockInterface $clock,
        private readonly ?InfirmerieEtatProvider $infirmerie = null,
        private readonly ?SejourInfirmerieRepository $sejours = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function instantane(): array
    {
        $now = $this->clock->now();
        $missions = $this->missions->filtrer(MissionStatut::actifs());
        $heros = $this->heroes->findAll();
        $mouvements = $this->mouvements->indexerDerniersParHero(array_map(static fn (Hero $h) => (int) $h->getId(), $heros));
        $etats = $this->etats->indexerParMission(array_map(static fn (Mission $m) => (int) $m->getId(), $missions));
        $sejours = $this->sejours?->findActifs() ?? [];
        $sejourParHero = [];
        foreach ($sejours as $sejour) {
            $sejourParHero[$sejour->getHero()->getId()] = $sejour;
        }

        return [
            'genereLe' => $now->format(\DATE_ATOM),
            'trajet' => 'Simulation en ligne droite (grand cercle), pas un itinéraire routier.',
            'heros' => array_values(array_filter(array_map(fn (Hero $h) => $this->hero($h, $mouvements[$h->getId()] ?? null, $now, $sejourParHero[$h->getId()] ?? null), $heros))),
            'infirmerie' => $this->infirmerie?->instantane($now, $sejours),
            'missions' => array_map(fn (Mission $m) => $this->mission($m, $etats[$m->getId()] ?? null, $now), $missions),
            'alertes' => array_map(static fn ($a) => [
                'id' => $a->getId(),
                'titre' => $a->getTitre(),
                'message' => $a->getMessage(),
                'niveau' => $a->getNiveau()->value,
                'tone' => $a->getNiveau()->tone(),
                'code' => $a->getCode(),
                'missionId' => $a->getMission()?->getId(),
                'date' => $a->getCreatedAt()->format(\DATE_ATOM),
            ], $this->alertes->findActives(5)),
        ];
    }

    /** @return array<string, mixed>|null null si le héros n'a aucune position connue */
    private function hero(Hero $hero, ?HeroMovement $mouvement, \DateTimeImmutable $now, ?SejourInfirmerie $sejour = null): ?array
    {
        $affectation = $hero->getAffectationActive();
        $mission = $affectation?->getMission();
        $deplacement = null;
        $position = $hero->getCoordonnees();

        if (null !== $mouvement && null !== $mission && $mouvement->getMission() === $mission) {
            $etat = $this->deplacements->etatA($mouvement, $now);
            $position = $etat->position;
            $deplacement = [
                'statut' => $mouvement->getStatut()->value,
                'statutLabel' => $etat->arrive && $mouvement->isEnCours() ? 'Arrivée imminente' : $mouvement->getStatut()->label(),
                'progression' => $etat->progression(),
                'distanceTotaleKm' => round($mouvement->getDistanceKm(), 1),
                'distanceRestanteKm' => round($etat->distanceRestanteKm, 1),
                'secondesRestantes' => $etat->secondesRestantes,
                'arriveePrevue' => $mouvement->getDateArriveePrevue()->format(\DATE_ATOM),
                'arriveeReelle' => $mouvement->getDateArriveeReelle()?->format(\DATE_ATOM),
                'vitesse' => $mouvement->getVitesse(),
                'depart' => $mouvement->getDepart()->toArray(),
                'arrivee' => $mouvement->getArrivee()->toArray(),
            ];
        }
        // Rapatriement : le héros est affiché sur son trajet de retour, destination le QG.
        $retour = null !== $sejour ? $this->infirmerie?->retour($sejour, $now) : null;
        if (null !== $retour) {
            $position = \App\Geo\Coordonnees::depuisDecimaux($retour['position']['lat'], $retour['position']['lng']) ?? $position;
            $deplacement = $retour;
        }
        if (null === $position) {
            return null;
        }

        $distanceMission = null;
        if (null !== $mission && null !== ($cible = $mission->getCoordonnees())) {
            $distanceMission = $this->distances->distanceArrondie($position, $cible);
        }

        return [
            'id' => $hero->getId(),
            'nom' => $hero->getNom(),
            'initiales' => $hero->getInitiales(),
            'role' => $hero->getRole()->label(),
            'statut' => $hero->getStatut()->value,
            'statutLabel' => $hero->getStatut()->label(),
            'tone' => $hero->getStatut()->tone(),
            'pv' => (int) $hero->getPointsDeVie(),
            'pvMax' => $hero->getPointsDeVieMaximum(),
            'sante' => $hero->getSantePourcentage(),
            'santeLabel' => $this->sante->libelle($hero),
            'critique' => $this->sante->estCritique($hero),
            'position' => $position->toArray(),
            'missionId' => $mission?->getId(),
            'affectation' => $affectation?->getStatut()->label(),
            'distanceMissionKm' => $distanceMission,
            'deplacement' => $deplacement,
            'infirmerie' => null === $sejour ? null : [
                'statut' => $sejour->getStatut()->value,
                'statutLabel' => $sejour->getStatut()->label(),
                'motif' => $sejour->getMotif()->label(),
            ],
            'url' => $this->urls->generate('app_hero_show', ['id' => $hero->getId()]),
        ];
    }

    /** @return array<string, mixed> */
    private function mission(Mission $mission, ?MissionSimulationState $etat, \DateTimeImmutable $now): array
    {
        $coordonnees = $mission->getCoordonnees();
        $echeance = $mission->getDateFinPrevue();
        $menace = $etat?->getDateEngagement() ? $etat->getMenaceActuelle() : null;

        return [
            'id' => $mission->getId(),
            'nom' => $mission->getNom(),
            'localisation' => $mission->getLocalisation(),
            'position' => $coordonnees?->toArray(),
            'statut' => $mission->getStatut()->value,
            'statutLabel' => $mission->getStatut()->label(),
            'phase' => $this->simulation->phase($mission, $etat),
            'urgence' => $mission->getNiveauUrgence()->value,
            'urgenceLabel' => $mission->getNiveauUrgence()->label(),
            'critique' => $mission->isCritique(),
            'difficulte' => $mission->getDifficulte()?->label(),
            'taux' => (int) $mission->getTauxDeReussite(),
            'progression' => (int) round($this->simulation->progressionObligatoire($mission, $etat)),
            'menace' => $menace,
            'menaceLabel' => null !== $menace ? $this->menaces->libelle($menace) : null,
            'secondesRestantes' => null !== $echeance ? max(0, $echeance->getTimestamp() - $now->getTimestamp()) : null,
            'objectifs' => array_map(static fn ($o) => [
                'id' => $o->getId(),
                'titre' => $o->getTitre(),
                'progression' => $o->isTermine() ? 100 : (int) floor(null !== $etat && null !== $o->getId() ? $etat->getProgressionPrecise($o->getId(), $o->getProgression()) : $o->getProgression()),
                'termine' => $o->isTermine(),
                'obligatoire' => $o->isObligatoire(),
                'etat' => $o->getEtat(),
                'aptitude' => $o->getAptitudeRequise()?->getNom(),
            ], array_values($mission->getObjectifs()->toArray())),
            'vilains' => $this->vilains($mission, $coordonnees),
            'equipe' => array_map(static fn (Hero $h) => (int) $h->getId(), $mission->getHerosAffectes()),
            'journal' => $etat?->getJournal() ?? [],
            'url' => $this->urls->generate('app_mission_show', ['id' => $mission->getId()]),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function vilains(Mission $mission, ?Coordonnees $centre): array
    {
        $resultat = [];
        $n = max(1, $mission->getVillains()->count());
        foreach (array_values($mission->getVillains()->toArray()) as $i => $villain) {
            $angle = 2 * M_PI * $i / $n + M_PI / 4;
            $resultat[] = [
                'id' => $villain->getId(),
                'nom' => $villain->getNom(),
                'menace' => $villain->getNiveauMenace(),
                'statut' => $villain->getStatut()->label(),
                'position' => null === $centre ? null : [
                    'lat' => round($centre->latitude + self::DECALAGE_VILAIN * sin($angle), 7),
                    'lng' => round($centre->longitude + self::DECALAGE_VILAIN * cos($angle) / max(0.2, cos(deg2rad($centre->latitude))), 7),
                ],
            ];
        }

        return $resultat;
    }
}
