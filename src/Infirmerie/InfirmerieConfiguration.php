<?php

namespace App\Infirmerie;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Seuils médicaux de l'infirmerie (les durées de soin relèvent de GameTimeConfiguration),
 * tous surchargeables par variables d'environnement
 * (.env / .env.local) : aucune valeur n'est codée en dur dans les services métier.
 *
 * Données fictives : elles décrivent l'état de personnages, jamais de personnes réelles.
 */
final readonly class InfirmerieConfiguration
{
    public function __construct(
        /** Santé (%) en dessous ou égale à laquelle le héros est rapatrié. */
        #[Autowire('%env(float:INFIRMERIE_SANTE_CRITIQUE)%')]
        public float $santeCritique = 20.0,
        /** Fatigue (0-100) à partir de laquelle le héros est rapatrié. */
        #[Autowire('%env(float:INFIRMERIE_FATIGUE_CRITIQUE)%')]
        public float $fatigueCritique = 90.0,
        /** Code de l'aptitude de soutien médical (jamais le nom d'un héros). */
        #[Autowire('%env(INFIRMERIE_APTITUDE_SOUTIEN)%')]
        public string $aptitudeSoutien = 'SUPPORT_MEDICAL',
        /** Nombre de lits. Au-delà, les admissions restent possibles mais le soin est plus long. */
        #[Autowire('%env(int:INFIRMERIE_CAPACITE)%')]
        public int $capacite = 6,
        /** true : sortie dès le rétablissement ; false : validation par un opérateur héros. */
        #[Autowire('%env(bool:INFIRMERIE_SORTIE_AUTOMATIQUE)%')]
        public bool $sortieAutomatique = false,
        /** Fatigue gagnée par étape de simulation (5 s simulées) pendant l'engagement. */
        #[Autowire('%env(float:INFIRMERIE_FATIGUE_PAR_ETAPE)%')]
        public float $fatigueParEtape = 0.1,
    ) {
    }

    /** @return array<string, mixed> */
    public function versTableau(): array
    {
        return [
            'santeCritique' => $this->santeCritique,
            'fatigueCritique' => $this->fatigueCritique,
            'regleSortie' => HeroMedicalEvaluationService::REGLE_SORTIE,
            'capacite' => $this->capacite,
            'sortieAutomatique' => $this->sortieAutomatique,
        ];
    }
}
