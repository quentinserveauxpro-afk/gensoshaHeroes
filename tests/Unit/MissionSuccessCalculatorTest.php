<?php

namespace App\Tests\Unit;

use App\Entity\Synergie;
use App\Enum\Difficulte;
use App\Enum\Statistique;
use App\Service\Mission\MissionSuccessCalculator;
use App\Tests\Support\Builder;
use App\Tests\Support\ServiceFactory;
use PHPUnit\Framework\TestCase;

final class MissionSuccessCalculatorTest extends TestCase
{
    private MissionSuccessCalculator $calc;

    protected function setUp(): void
    {
        $this->calc = ServiceFactory::calculator();
    }

    public function testEquipeVideDonneZero(): void
    {
        self::assertSame(0, $this->calc->taux(Builder::mission(), []));
    }

    public function testFormuleDocumenteeSurUnCasSimple(): void
    {
        // Héros parfait, sur place, niveau suffisant, difficulté C (×1,00), sans exigence :
        // S = 80, N = 100, H = 100, D = 100 → B = 0,45×80 + 0,15×100 + 0,20×100 + 0,20×100 = 91
        $mission = Builder::mission(['difficulte' => Difficulte::C]);
        $hero = Builder::hero(['niveau' => 20]);

        $pronostic = $this->calc->calculer($mission, [$hero]);
        self::assertSame(91, $pronostic->taux);
        self::assertSame(80.0, $pronostic->scoreStatistiques);
        self::assertSame(100.0, $pronostic->scoreProximite);
    }

    public function testResultatToujoursBorne(): void
    {
        $facile = Builder::mission(['difficulte' => Difficulte::D]);
        $heros = [Builder::hero(['combat' => 100, 'intelligence' => 100, 'charisme' => 100, 'mobilite' => 100, 'vigueur' => 100, 'niveau' => 100])];
        Builder::bonus($heros[0], Statistique::GLOBAL, 50);
        self::assertSame(100, $this->calc->taux($facile, $heros));

        $impossible = Builder::mission(['difficulte' => Difficulte::S, 'x' => 0.0, 'y' => 0.0]);
        for ($i = 0; $i < 5; ++$i) {
            $impossible->addVillain(Builder::villain(10, 100, 100));
        }
        $faible = Builder::hero(['combat' => 0, 'intelligence' => 0, 'charisme' => 0, 'mobilite' => 0, 'vigueur' => 0, 'niveau' => 1, 'pv' => 1, 'x' => 100.0, 'y' => 100.0]);
        self::assertSame(0, $this->calc->taux($impossible, [$faible]));
    }

    public function testEquipeTropPetitePenalisee(): void
    {
        $mission = Builder::mission(['min' => 2, 'max' => 4]);
        $hero = Builder::hero();
        $seul = $this->calc->taux($mission, [$hero]);
        $duo = $this->calc->taux($mission, [$hero, Builder::hero()]);

        self::assertLessThan($duo, $seul);
        self::assertEqualsWithDelta($duo / 2, $seul, 1.0);
    }

    public function testEquipeTropGrandePenalisee(): void
    {
        $mission = Builder::mission(['min' => 1, 'max' => 2]);
        $deux = $this->calc->taux($mission, [Builder::hero(), Builder::hero()]);
        $quatre = $this->calc->taux($mission, [Builder::hero(), Builder::hero(), Builder::hero(), Builder::hero()]);

        self::assertSame($deux - 2 * (int) MissionSuccessCalculator::PENALITE_SURNOMBRE, $quatre);
    }

    public function testDifficulteNiveauSanteDistance(): void
    {
        $hero = Builder::hero(['niveau' => 30]);
        $c = $this->calc->taux(Builder::mission(['difficulte' => Difficulte::C]), [$hero]);
        $s = $this->calc->taux(Builder::mission(['difficulte' => Difficulte::S]), [$hero]);
        self::assertLessThan($c, $s, 'Une difficulté supérieure réduit le taux.');

        $mission = Builder::mission();
        $sain = $this->calc->taux($mission, [Builder::hero()]);
        self::assertLessThan($sain, $this->calc->taux($mission, [Builder::hero(['pv' => 20])]), 'Un héros blessé réduit le taux.');
        self::assertLessThan($sain, $this->calc->taux($mission, [Builder::hero(['x' => 90.0])]), 'Un héros éloigné réduit le taux.');
        self::assertLessThan($sain, $this->calc->taux($mission, [Builder::hero(['niveau' => 5])]), 'Un niveau trop faible réduit le taux.');
    }

    public function testMenaceDesVilains(): void
    {
        $mission = Builder::mission();
        $hero = Builder::hero(['combat' => 60]);
        $sans = $this->calc->taux($mission, [$hero]);
        $mission->addVillain(Builder::villain(6, 90, 40));

        $pronostic = $this->calc->calculer($mission, [$hero]);
        // menace = 6 + 0,3 × (90 − 60) = 15
        self::assertSame(15.0, $pronostic->penaliteMenace);
        self::assertSame($sans - 15, $pronostic->taux);
    }

    public function testBonusActifsAugmententLeTaux(): void
    {
        $mission = Builder::mission(['combatRequis' => 90]);
        $hero = Builder::hero(['combat' => 70]);
        $sans = $this->calc->taux($mission, [$hero]);
        Builder::bonus($hero, Statistique::COMBAT, 20);

        self::assertGreaterThan($sans, $this->calc->taux($mission, [$hero]));
    }

    public function testExigencesStatistiquesEtAptitudes(): void
    {
        $vol = Builder::aptitude('VOL');
        $mission = Builder::mission(['combatRequis' => 90, 'mobiliteRequis' => 70]);
        $mission->addAptitudeRequise($vol);

        $hero = Builder::hero(['combat' => 70, 'mobilite' => 80]);
        $pronostic = $this->calc->calculer($mission, [$hero]);
        self::assertSame(['COMBAT' => ['requis' => 90, 'actuel' => 70]], $pronostic->statsInsuffisantes);
        self::assertSame([$vol], $pronostic->aptitudesManquantes);

        $volant = Builder::hero(['combat' => 70, 'mobilite' => 80, 'aptitudes' => [$vol]]);
        self::assertSame($pronostic->taux + 10, $this->calc->taux($mission, [$volant]));
    }

    public function testSynergieEtRecalculQuandLaCompositionChange(): void
    {
        $mission = Builder::mission(['difficulte' => Difficulte::A]);
        $a = Builder::hero(['niveau' => 40]);
        $b = Builder::hero(['niveau' => 40]);
        $c = Builder::hero(['niveau' => 40]);
        (new Synergie())->setNom('Duo')->setStatistique(Statistique::GLOBAL)->setValeurBonus(8)->addHero($a)->addHero($b);

        $sansSynergie = $this->calc->calculer($mission, [$a, $c]);
        $avecSynergie = $this->calc->calculer($mission, [$a, $b]);

        self::assertSame(0, $sansSynergie->bonusSynergieTotal);
        self::assertSame(8, $avecSynergie->bonusSynergieTotal);
        self::assertSame($sansSynergie->taux + 8, $avecSynergie->taux);
        self::assertSame('Bonne', $avecSynergie->qualiteSynergie());
    }
}
