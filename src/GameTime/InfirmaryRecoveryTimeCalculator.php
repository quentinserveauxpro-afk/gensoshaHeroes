<?php

namespace App\GameTime;

use App\Entity\Hero;
use App\Entity\SejourInfirmerie;
use App\Enum\MotifInfirmerie;
use App\Infirmerie\ContexteRecuperation;
use App\Infirmerie\HeroMedicalEvaluationService;
use App\Infirmerie\InfirmerieConfiguration;
use App\Service\Mission\EffectiveStatsResolver;

/**
 * Durée totale (en secondes réelles) d'un traitement à l'infirmerie, fixée au début du soin
 * à partir de l'état du héros à son admission.
 *
 *   gravité   = (100 − santé %) / 100 × 0,60 + fatigue / 100 × 0,40
 *   base      = min + gravité × (max − min)
 *   vigueur   = 1,10 − vigueur effective / 100 × 0,20        (bonus de récupération actifs inclus)
 *   admission = modificateurCritique si santé critique ou 0 PV, modificateurBlessure pour une
 *               blessure, 1 sinon
 *   soutien   = modificateurSoutien si un autre héros possédant l'aptitude de soutien médical
 *               est disponible au QG ; demi-effet si seul le patient la possède ; 1 sinon
 *   capacité  = modificateurSaturation si l'infirmerie dépasse sa capacité, 1 sinon
 *   durée     = clamp(round(base × vigueur × admission × soutien × capacité), min, max)
 *
 * Bornes par défaut : 60 s à 900 s (15 min). Déterministe, jamais négative.
 *
 * Répartition (durees) : la santé et la fatigue récupèrent chacune à leur rythme, la plus
 * lourde des deux occupant toute la durée :
 *   poids santé   = (100 − santé %) / 100 × 0,60 × admission     (0 si santé maximale)
 *   poids fatigue = fatigue / 100 × 0,40                         (0 si fatigue nulle)
 *   durée santé   = durée × poids santé / max(poids)  ;  durée fatigue idem
 * La sortie a lieu à la fin de la plus longue (= durée totale).
 */
final readonly class InfirmaryRecoveryTimeCalculator
{
    public function __construct(
        private GameTimeConfiguration $config,
        private InfirmerieConfiguration $infirmerie,
        private EffectiveStatsResolver $stats,
    ) {
    }

    public function duree(SejourInfirmerie $sejour, ContexteRecuperation $contexte, \DateTimeImmutable $now): int
    {
        $hero = $sejour->getHero();
        $sante = HeroMedicalEvaluationService::santePourcentage($sejour->getSanteAdmission(), $hero->getPointsDeVieMaximum());
        $fatigue = HeroMedicalEvaluationService::bornerFatigue($sejour->getFatigueAdmission());
        $gravite = (100 - $sante) / 100 * 0.60 + $fatigue / 100 * 0.40;
        $base = $this->config->infirmerieMin + $gravite * ($this->config->infirmerieMax - $this->config->infirmerieMin);

        return HeroTravelTimeCalculator::borner(
            $base * array_product($this->modificateurs($sejour, $contexte, $now)),
            $this->config->infirmerieMin,
            $this->config->infirmerieMax,
        );
    }

    /**
     * Durées propres à la santé et à la fatigue (secondes) ; total = la plus longue, bornée.
     *
     * @return array{sante: int, fatigue: int, total: int}
     */
    public function durees(SejourInfirmerie $sejour, ContexteRecuperation $contexte, \DateTimeImmutable $now): array
    {
        $total = $this->duree($sejour, $contexte, $now);
        $hero = $sejour->getHero();
        $sante = HeroMedicalEvaluationService::santePourcentage($sejour->getSanteAdmission(), $hero->getPointsDeVieMaximum());
        $fatigue = HeroMedicalEvaluationService::bornerFatigue($sejour->getFatigueAdmission());
        $poidsSante = HeroMedicalEvaluationService::santeRetablie($sejour->getSanteAdmission(), $hero->getPointsDeVieMaximum())
            ? 0.0 : (100 - $sante) / 100 * 0.60 * $this->modificateurs($sejour, $contexte, $now)['admission'];
        $poidsFatigue = $fatigue / 100 * 0.40;
        $plusLourd = max($poidsSante, $poidsFatigue);
        if ($plusLourd <= 0) {
            return ['sante' => 0, 'fatigue' => 0, 'total' => $total];
        }

        return [
            'sante' => $poidsSante > 0 ? max(1, (int) round($total * $poidsSante / $plusLourd)) : 0,
            'fatigue' => $poidsFatigue > 0 ? max(1, (int) round($total * $poidsFatigue / $plusLourd)) : 0,
            'total' => $total,
        ];
    }

    /** @return array{vigueur: float, admission: float, soutien: float, capacite: float} */
    public function modificateurs(SejourInfirmerie $sejour, ContexteRecuperation $contexte, \DateTimeImmutable $now): array
    {
        $hero = $sejour->getHero();
        $vigueur = max(0, min(100, $this->stats->statsEffectives($hero, $now)['VIGUEUR'] ?? $hero->getVigueur()));
        $sante = HeroMedicalEvaluationService::santePourcentage($sejour->getSanteAdmission(), $hero->getPointsDeVieMaximum());
        $soutien = $this->config->modificateurSoutien;

        return [
            'vigueur' => 1.10 - $vigueur / 100 * 0.20,
            'admission' => match (true) {
                $sejour->getSanteAdmission() <= 0, $sante <= $this->infirmerie->santeCritique, MotifInfirmerie::SANTE_CRITIQUE === $sejour->getMotif() => $this->config->modificateurCritique,
                MotifInfirmerie::BLESSURE === $sejour->getMotif() => $this->config->modificateurBlessure,
                default => 1.0,
            },
            'soutien' => match (true) {
                $contexte->soutienPour((int) $hero->getId()) => $soutien,
                $this->possedeSoutien($hero) => 1 - (1 - $soutien) / 2,
                default => 1.0,
            },
            'capacite' => $contexte->occupation > $this->infirmerie->capacite ? $this->config->modificateurSaturation : 1.0,
        ];
    }

    /** Aptitude de soutien médical (code configurable), jamais le nom d'un héros. */
    public function possedeSoutien(Hero $hero): bool
    {
        foreach ($hero->getAptitudes() as $aptitude) {
            if ($aptitude->getCode() === $this->infirmerie->aptitudeSoutien) {
                return true;
            }
        }

        return false;
    }
}
