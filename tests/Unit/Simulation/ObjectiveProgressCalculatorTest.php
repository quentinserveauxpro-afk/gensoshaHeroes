<?php

namespace App\Tests\Unit\Simulation;

use App\Entity\ObjectifMission;
use App\Enum\Statistique;
use App\Simulation\ObjectiveProgressCalculator;
use App\Tests\Support\Builder;
use PHPUnit\Framework\TestCase;

final class ObjectiveProgressCalculatorTest extends TestCase
{
    private ObjectiveProgressCalculator $calc;
    /** @var array<string, int> */
    private array $puissance = ['COMBAT' => 80, 'INTELLIGENCE' => 70, 'CHARISME' => 60, 'MOBILITE' => 75, 'VIGUEUR' => 80];

    protected function setUp(): void
    {
        $this->calc = new ObjectiveProgressCalculator();
    }

    private function objectif(): ObjectifMission
    {
        return (new ObjectifMission())->setTitre('Sécuriser')->setObligatoire(true);
    }

    public function testProgressionPositiveAvecEquipeSurPlace(): void
    {
        $r = $this->calc->calculer($this->objectif(), Builder::mission(), [Builder::hero()], $this->puissance, 30.0);
        self::assertFalse($r->bloque);
        self::assertGreaterThan(0, $r->gain);
    }

    public function testBloqueSansHerosSurPlace(): void
    {
        $r = $this->calc->calculer($this->objectif(), Builder::mission(), [], $this->puissance, 30.0);
        self::assertTrue($r->bloque);
        self::assertSame(0.0, $r->gain);
    }

    public function testPrerequisDeCompetenceBloqueLObjectif(): void
    {
        $vol = Builder::aptitude('VOL');
        $objectif = $this->objectif()->setAptitudeRequise($vol);

        $sans = $this->calc->calculer($objectif, Builder::mission(), [Builder::hero()], $this->puissance, 30.0);
        $avec = $this->calc->calculer($objectif, Builder::mission(), [Builder::hero(['aptitudes' => [$vol]])], $this->puissance, 30.0);

        self::assertTrue($sans->bloque);
        self::assertSame(0.0, $sans->gain);
        self::assertStringContainsString($vol->getNom(), (string) $sans->raison);
        self::assertGreaterThan(0, $avec->gain);
    }

    public function testCompetenceDeMissionManquanteRendLaProgressionTresLente(): void
    {
        $cryo = Builder::aptitude('CRYOGENIE');
        $mission = Builder::mission()->addAptitudeRequise($cryo);
        $lent = $this->calc->calculer($this->objectif(), $mission, [Builder::hero()], $this->puissance, 30.0);
        $normal = $this->calc->calculer($this->objectif(), $mission, [Builder::hero(['aptitudes' => [$cryo]])], $this->puissance, 30.0);

        self::assertFalse($lent->bloque);
        self::assertEqualsWithDelta($normal->gain * ObjectiveProgressCalculator::FACTEUR_APTITUDE_MANQUANTE, $lent->gain, 0.001);
    }

    public function testLaMenaceDesVilainsRalentitLaProgression(): void
    {
        $faible = $this->calc->calculer($this->objectif(), Builder::mission(), [Builder::hero()], $this->puissance, 10.0);
        $forte = $this->calc->calculer($this->objectif(), Builder::mission(), [Builder::hero()], $this->puissance, 110.0);
        self::assertGreaterThan($forte->gain, $faible->gain);
    }

    public function testStatistiqueCleEtEquipeInfluencentLeGain(): void
    {
        $objectif = $this->objectif()->setStatistiqueCle(Statistique::INTELLIGENCE);
        $fort = $this->calc->calculer($objectif, Builder::mission(), [Builder::hero()], ['INTELLIGENCE' => 95] + $this->puissance, 30.0);
        $faible = $this->calc->calculer($objectif, Builder::mission(), [Builder::hero()], ['INTELLIGENCE' => 20] + $this->puissance, 30.0);
        self::assertGreaterThan($faible->gain, $fort->gain);

        self::assertGreaterThan($this->calc->facteurEquipe(1, 2), $this->calc->facteurEquipe(2, 2));
    }

    public function testObjectifTermineNeProgressePlus(): void
    {
        $r = $this->calc->calculer($this->objectif()->setTermine(true), Builder::mission(), [Builder::hero()], $this->puissance, 30.0);
        self::assertSame(0.0, $r->gain);
    }
}
