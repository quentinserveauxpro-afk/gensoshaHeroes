<?php

namespace App\Tests\Unit;

use App\Entity\Hero;
use App\Entity\Synergie;
use App\Enum\HeroRole;
use App\Enum\HeroStatut;
use App\Enum\Statistique;
use App\Repository\AffectationMissionRepository;
use App\Service\Hero\HeroRecommendationService;
use App\Service\Map\PositionDistanceCalculator;
use App\Service\Mission\EffectiveStatsResolver;
use App\Service\Mission\SynergyResolver;
use App\Tests\Support\Builder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class HeroRecommendationServiceTest extends TestCase
{
    private function service(): HeroRecommendationService
    {
        $repo = $this->createStub(AffectationMissionRepository::class);
        $repo->method('indexerActivesParHero')->willReturn([]);

        return new HeroRecommendationService(new PositionDistanceCalculator(), new EffectiveStatsResolver(), new SynergyResolver(), $repo, new MockClock());
    }

    private static function avecId(Hero $hero, int $id): Hero
    {
        (new \ReflectionProperty(Hero::class, 'id'))->setValue($hero, $id);

        return $hero;
    }

    public function testClassementParDisponibiliteSanteDistanceEtStats(): void
    {
        $mission = Builder::mission(['combatRequis' => 85, 'x' => 50.0, 'y' => 50.0]);
        $ideal = self::avecId(Builder::hero(['nom' => 'Ideal', 'combat' => 90]), 1);
        $loin = self::avecId(Builder::hero(['nom' => 'Loin', 'combat' => 90, 'x' => 95.0]), 2);
        $faible = self::avecId(Builder::hero(['nom' => 'Faible', 'combat' => 40]), 3);
        $blesse = self::avecId(Builder::hero(['nom' => 'Blessé', 'combat' => 99, 'statut' => HeroStatut::BLESSE]), 4);

        $classement = array_map(static fn ($r) => $r->hero->getNom(), $this->service()->recommander($mission, [$blesse, $faible, $loin, $ideal]));

        self::assertSame(['Ideal', 'Loin', 'Faible', 'Blessé'], $classement);
    }

    public function testInelegibleAvecMotif(): void
    {
        $r = $this->service()->evaluer(Builder::mission(), Builder::hero(['statut' => HeroStatut::REPOS]));
        self::assertFalse($r->eligible);
        self::assertSame('Repos', $r->motifIneligibilite);
    }

    public function testRoleManquantAptitudeEtSynergie(): void
    {
        $vol = Builder::aptitude('VOL');
        $mission = Builder::mission();
        $mission->addAptitudeRequise($vol);
        $membre = self::avecId(Builder::hero(['role' => HeroRole::ASSAUT]), 1);
        $candidat = self::avecId(Builder::hero(['role' => HeroRole::SOUTIEN, 'aptitudes' => [$vol]]), 2);
        (new Synergie())->setNom('Duo')->setStatistique(Statistique::GLOBAL)->setValeurBonus(6)->addHero($membre)->addHero($candidat);
        $neutre = self::avecId(Builder::hero(['role' => HeroRole::ASSAUT]), 3);

        $service = $this->service();
        $rCandidat = $service->evaluer($mission, $candidat, [$membre]);
        $rNeutre = $service->evaluer($mission, $neutre, [$membre]);

        self::assertGreaterThan($rNeutre->score, $rCandidat->score);
        self::assertContains('Apporte le rôle Soutien', $rCandidat->raisons);
        self::assertContains('Apporte Vol', $rCandidat->raisons);
        self::assertContains('Synergie +6', $rCandidat->raisons);
        self::assertSame($candidat, $service->meilleure($mission, [$neutre, $candidat], [$membre])?->hero);
    }

    public function testLeMembreDejaDansLEquipeNestPasPropose(): void
    {
        $membre = self::avecId(Builder::hero(), 1);
        self::assertSame([], $this->service()->recommander(Builder::mission(), [$membre], [$membre]));
    }
}
