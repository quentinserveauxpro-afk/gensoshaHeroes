<?php

namespace App\Infirmerie;

use App\Entity\Hero;
use App\Entity\SejourInfirmerie;
use App\Enum\StatutSejour;
use App\Repository\SejourInfirmerieRepository;

/**
 * Indisponibilité médicale d'un héros pour le Squad Builder et le déploiement.
 * Un héros est indisponible s'il a un séjour actif, ou si sa santé ou sa fatigue est
 * critique (il sera rapatrié au prochain passage du worker).
 */
final readonly class DisponibiliteMedicale
{
    public function __construct(
        private SejourInfirmerieRepository $sejours,
        private HeroMedicalEvaluationService $medical,
    ) {
    }

    /**
     * @param list<int> $heroIds
     *
     * @return array<int, SejourInfirmerie>
     */
    public function sejoursActifs(array $heroIds): array
    {
        return $this->sejours->indexerActifsParHero(array_values(array_filter($heroIds)));
    }

    /** Motif d'indisponibilité lisible, ou null si le héros est médicalement apte. */
    public function motif(Hero $hero, ?SejourInfirmerie $sejour = null, bool $chercher = true): ?string
    {
        if (null === $sejour && $chercher && null !== $hero->getId()) {
            $sejour = $this->sejours->findActifPourHero($hero);
        }
        if (null !== $sejour) {
            return match ($sejour->getStatut()) {
                StatutSejour::RETOUR_EN_ATTENTE, StatutSejour::RETOUR_QG => 'Retour vers le QG',
                StatutSejour::RETABLI => 'Rétabli, sortie de l\'infirmerie à valider',
                default => 'Traitement médical',
            }.' ('.mb_strtolower($sejour->getMotif()->label()).')';
        }

        return match (true) {
            $this->medical->estSanteCritique($hero) => 'Santé critique',
            $this->medical->estFatigueCritique($hero) => 'Fatigue excessive',
            default => null,
        };
    }
}
