<?php

namespace App\Infirmerie;

use App\Entity\Hero;
use App\Entity\SejourInfirmerie;
use App\Geo\DistanceCalculator;
use App\Repository\HeroRepository;
use App\Repository\SejourInfirmerieRepository;

/**
 * Conditions de l'infirmerie (soutiens médicaux disponibles au QG, occupation), calculées
 * en deux requêtes pour tous les séjours d'un passage.
 */
final readonly class InfirmerieContexteProvider
{
    public function __construct(
        private HeroRepository $heroes,
        private SejourInfirmerieRepository $sejours,
        private InfirmerieConfiguration $config,
        private QgConfiguration $qg,
        private DistanceCalculator $distances,
    ) {
    }

    /** @param list<SejourInfirmerie>|null $actifs séjours actifs déjà chargés (sinon relus) */
    public function contexte(?array $actifs = null, int $enAttente = 0): ContexteRecuperation
    {
        $actifs ??= $this->sejours->findActifs();
        $patients = array_map(static fn (SejourInfirmerie $s) => (int) $s->getHero()->getId(), $actifs);

        return new ContexteRecuperation($this->soutiensDisponibles($patients), \count($actifs) + $enAttente);
    }

    /**
     * Héros disponibles au QG (à moins de RAYON_KM, ou sans position) possédant l'aptitude de soutien.
     *
     * @param list<int> $patients
     *
     * @return list<int>
     */
    public function soutiensDisponibles(array $patients = []): array
    {
        $qg = $this->qg->coordonnees();

        return array_values(array_map(static fn (Hero $h) => (int) $h->getId(), array_filter(
            $this->heroes->findAvecAptitude($this->config->aptitudeSoutien),
            fn (Hero $h) => $h->isDisponible() && !\in_array((int) $h->getId(), $patients, true)
                && (null === $h->getCoordonnees() || $this->distances->distanceKm($h->getCoordonnees(), $qg) <= QgConfiguration::RAYON_KM),
        )));
    }
}
