<?php

namespace App\Infirmerie;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Paramètres médicaux de l'infirmerie, tous surchargeables par variables d'environnement
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
        /** Santé (%) à atteindre pour être déclaré rétabli. */
        #[Autowire('%env(float:INFIRMERIE_SANTE_SORTIE)%')]
        public float $santeSortie = 100.0,
        /** Fatigue maximale pour être déclaré rétabli. */
        #[Autowire('%env(float:INFIRMERIE_FATIGUE_SORTIE)%')]
        public float $fatigueSortie = 20.0,
        /** Points de vie récupérés par période (avant modificateurs). */
        #[Autowire('%env(float:INFIRMERIE_SANTE_POINTS)%')]
        public float $santePoints = 2.0,
        /** Points de fatigue éliminés par période (avant modificateurs). */
        #[Autowire('%env(float:INFIRMERIE_FATIGUE_POINTS)%')]
        public float $fatiguePoints = 3.0,
        /** Durée d'une période de récupération, en minutes. */
        #[Autowire('%env(float:INFIRMERIE_PERIODE_MINUTES)%')]
        public float $periodeMinutes = 5.0,
        /** Accélération du temps de récupération (1 = temps réel ; 60 = 1 min réelle vaut 1 h). */
        #[Autowire('%env(float:INFIRMERIE_FACTEUR_TEMPS)%')]
        public float $facteurTemps = 1.0,
        /** Bonus relatif (+50 % = 0.5) apporté par un soutien médical disponible au QG. */
        #[Autowire('%env(float:INFIRMERIE_BONUS_SOUTIEN)%')]
        public float $bonusSoutien = 0.5,
        /** Code de l'aptitude ouvrant droit au bonus (jamais le nom d'un héros). */
        #[Autowire('%env(INFIRMERIE_APTITUDE_SOUTIEN)%')]
        public string $aptitudeSoutien = 'SUPPORT_MEDICAL',
        /** Nombre de lits. Au-delà, les admissions restent possibles mais la récupération ralentit. */
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

    /** Points de vie récupérés par minute simulée, avant modificateurs. */
    public function tauxSanteBase(): float
    {
        return $this->santePoints / max(0.001, $this->periodeMinutes);
    }

    /** Points de fatigue éliminés par minute simulée, avant modificateurs. */
    public function tauxFatigueBase(): float
    {
        return $this->fatiguePoints / max(0.001, $this->periodeMinutes);
    }

    /** @return array<string, mixed> */
    public function versTableau(): array
    {
        return [
            'santeCritique' => $this->santeCritique,
            'fatigueCritique' => $this->fatigueCritique,
            'santeSortie' => $this->santeSortie,
            'fatigueSortie' => $this->fatigueSortie,
            'santePoints' => $this->santePoints,
            'fatiguePoints' => $this->fatiguePoints,
            'periodeMinutes' => $this->periodeMinutes,
            'facteurTemps' => $this->facteurTemps,
            'bonusSoutien' => $this->bonusSoutien,
            'capacite' => $this->capacite,
            'sortieAutomatique' => $this->sortieAutomatique,
        ];
    }
}
