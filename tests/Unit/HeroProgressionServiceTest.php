<?php

namespace App\Tests\Unit;

use App\Service\Hero\HeroProgressionService;
use App\Tests\Support\Builder;
use PHPUnit\Framework\TestCase;

final class HeroProgressionServiceTest extends TestCase
{
    private HeroProgressionService $service;

    protected function setUp(): void
    {
        $this->service = new HeroProgressionService();
    }

    public function testAjoutSansChangementDeNiveau(): void
    {
        $hero = Builder::hero(['niveau' => 10, 'xp' => 100]);
        $resultat = $this->service->ajouterExperience($hero, 500);

        self::assertSame(0, $resultat->niveauxGagnes());
        self::assertSame(10, $hero->getNiveau());
        self::assertSame(600, $hero->getXp());
    }

    public function testChangementDeNiveauEtAmeliorationDesStats(): void
    {
        $hero = Builder::hero(['niveau' => 10, 'xp' => 3700, 'combat' => 50, 'pvMax' => 100]);
        // seuil(10) = 3750
        $resultat = $this->service->ajouterExperience($hero, 100);

        self::assertSame(1, $resultat->niveauxGagnes());
        self::assertSame(11, $hero->getNiveau());
        self::assertSame(50, $hero->getXp());
        self::assertSame(52, $hero->getCombat());
        self::assertSame(105, $hero->getPointsDeVieMaximum());
    }

    public function testPlusieursNiveauxDUnCoup(): void
    {
        $hero = Builder::hero(['niveau' => 1, 'xp' => 0]);
        // 375 (1→2) + 750 (2→3) + 1125 (3→4) = 2250
        $this->service->ajouterExperience($hero, 2300);

        self::assertSame(4, $hero->getNiveau());
        self::assertSame(50, $hero->getXp());
    }

    public function testLesStatistiquesNeDepassentPas100(): void
    {
        $hero = Builder::hero(['niveau' => 1, 'combat' => 99, 'vigueur' => 100]);
        $this->service->ajouterExperience($hero, 375 + 750);

        self::assertSame(100, $hero->getCombat());
        self::assertSame(100, $hero->getVigueur());
    }

    public function testNiveauMaximal(): void
    {
        $hero = Builder::hero(['niveau' => 100, 'xp' => 0]);
        $this->service->ajouterExperience($hero, 1_000_000);

        self::assertSame(100, $hero->getNiveau());
        self::assertSame($this->service->seuil(100), $hero->getXp());
    }

    public function testExperienceNegativeRefusee(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->ajouterExperience(Builder::hero(), -5);
    }

    public function testPourcentageNiveau(): void
    {
        $hero = Builder::hero(['niveau' => 48, 'xp' => 14250]);
        self::assertSame(18000, $this->service->seuil(48));
        self::assertSame(79, $this->service->pourcentageNiveau($hero));
    }
}
