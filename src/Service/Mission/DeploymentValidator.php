<?php

namespace App\Service\Mission;

use App\Entity\Hero;
use App\Entity\Mission;
use App\Enum\MissionStatut;
use App\Enum\Statistique;
use App\Infirmerie\DisponibiliteMedicale;
use App\Repository\AffectationMissionRepository;
use App\Service\Map\PositionDistanceCalculator;
use Psr\Clock\ClockInterface;

/**
 * Règles de déploiement, contrôlées côté serveur (jamais déléguées au JavaScript).
 *
 * Bloquant : mission close ou déjà déployée, équipe trop petite / trop grande, doublon,
 * héros indisponible, héros engagé dans une autre mission active, santé sous le seuil,
 * aptitude obligatoire manquante, statistique requise non atteinte par au moins un héros,
 * héros à l'infirmerie ou en état médical critique.
 */
final class DeploymentValidator
{
    public const FATIGUE_ALERTE = 70;
    public const DISTANCE_ALERTE_KM = 20.0;

    public function __construct(
        private readonly AffectationMissionRepository $affectations,
        private readonly MissionSuccessCalculator $calculator,
        private readonly EffectiveStatsResolver $statsResolver,
        private readonly PositionDistanceCalculator $distances,
        private readonly ClockInterface $clock,
        private readonly ?DisponibiliteMedicale $medical = null,
    ) {
    }

    /** @param list<Hero> $equipe */
    public function valider(Mission $mission, array $equipe): DeploymentReport
    {
        $erreurs = [];
        $avertissements = [];

        if ($mission->getStatut()->isClosed()) {
            $erreurs[] = sprintf('La mission est %s : déploiement impossible.', mb_strtolower($mission->getStatut()->label()));
        } elseif (MissionStatut::EN_COURS === $mission->getStatut()) {
            $erreurs[] = 'La mission est déjà déployée.';
        }

        $n = \count($equipe);
        $ids = array_map(static fn (Hero $h) => (int) $h->getId(), $equipe);
        if (\count(array_unique($ids)) !== $n) {
            $erreurs[] = 'Un même héros ne peut occuper deux emplacements.';
        }
        if ($n < $mission->getTailleEquipeMinimale()) {
            $erreurs[] = sprintf('Équipe trop petite : %d héros minimum (actuellement %d).', $mission->getTailleEquipeMinimale(), $n);
        }
        if ($n > $mission->getTailleEquipeMaximale()) {
            $erreurs[] = sprintf('Équipe trop grande : %d héros maximum (actuellement %d).', $mission->getTailleEquipeMaximale(), $n);
        }

        $engages = [];
        foreach ($this->affectations->findActivesPourHeros($ids, $mission) as $affectation) {
            $engages[$affectation->getHero()->getId()] = $affectation->getMission();
        }

        // Revalidation serveur de l'aptitude médicale (séjour à l'infirmerie, état critique).
        $sejours = $this->medical?->sejoursActifs($ids) ?? [];

        foreach ($equipe as $hero) {
            $nom = (string) $hero->getNom();
            $motifMedical = $this->medical?->motif($hero, $sejours[$hero->getId()] ?? null, false);
            if (null !== $motifMedical) {
                $erreurs[] = sprintf('%s est médicalement indisponible : %s.', $nom, mb_strtolower($motifMedical));
            } elseif (isset($engages[$hero->getId()])) {
                $erreurs[] = sprintf('%s est déjà engagé dans la mission « %s ».', $nom, $engages[$hero->getId()]->getNom());
            } elseif (!$hero->isDisponible()) {
                $erreurs[] = sprintf('%s est indisponible (%s).', $nom, mb_strtolower($hero->getStatut()->label()));
            }
            if ($hero->getSantePourcentage() < $mission->getSeuilSanteMinimal()) {
                $erreurs[] = sprintf('%s a une santé de %d %%, inférieure au seuil de %d %%.', $nom, $hero->getSantePourcentage(), $mission->getSeuilSanteMinimal());
            }
            if ($hero->getFatigue() >= self::FATIGUE_ALERTE) {
                $avertissements[] = sprintf('%s présente une fatigue élevée (%d %%).', $nom, $hero->getFatigue());
            }
            $distance = $this->distances->heroMission($hero, $mission);
            if ($distance > self::DISTANCE_ALERTE_KM) {
                $avertissements[] = sprintf('%s est éloigné de la mission (%.1f km).', $nom, $distance);
            }
        }

        if ($n > 0) {
            foreach ($this->calculator->aptitudesManquantes($mission, $equipe) as $aptitude) {
                $erreurs[] = sprintf('Aptitude obligatoire manquante : %s.', $aptitude->getNom());
            }
            $now = $this->clock->now();
            $meilleures = [];
            foreach ($equipe as $hero) {
                foreach ($this->statsResolver->statsEffectives($hero, $now) as $stat => $valeur) {
                    $meilleures[$stat] = max($meilleures[$stat] ?? 0, $valeur);
                }
            }
            foreach ($mission->getStatsRequises() as $stat => $requis) {
                if (($meilleures[$stat] ?? 0) < $requis) {
                    $erreurs[] = sprintf('Exigence non satisfaite : %s ≥ %d (meilleur héros : %d).', Statistique::from($stat)->label(), $requis, $meilleures[$stat] ?? 0);
                }
            }
        }

        return new DeploymentReport($erreurs, $avertissements);
    }
}
