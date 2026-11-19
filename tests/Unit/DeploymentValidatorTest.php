<?php

namespace App\Tests\Unit;

use App\Entity\AffectationMission;
use App\Entity\Hero;
use App\Enum\HeroStatut;
use App\Enum\MissionStatut;
use App\Repository\AffectationMissionRepository;
use App\Service\Map\PositionDistanceCalculator;
use App\Service\Mission\DeploymentValidator;
use App\Service\Mission\EffectiveStatsResolver;
use App\Tests\Support\Builder;
use App\Tests\Support\ServiceFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class DeploymentValidatorTest extends TestCase
{
    /** @param list<AffectationMission> $engagements */
    private function validator(array $engagements = []): DeploymentValidator
    {
        $repo = $this->createStub(AffectationMissionRepository::class);
        $repo->method('findActivesPourHeros')->willReturn($engagements);

        return new DeploymentValidator($repo, ServiceFactory::calculator(), new EffectiveStatsResolver(), new PositionDistanceCalculator(), new MockClock());
    }

    private static function avecId(Hero $hero, int $id): Hero
    {
        (new \ReflectionProperty(Hero::class, 'id'))->setValue($hero, $id);

        return $hero;
    }

    public function testEquipeValide(): void
    {
        $mission = Builder::mission(['min' => 2, 'max' => 3]);
        $rapport = $this->validator()->valider($mission, [self::avecId(Builder::hero(), 1), self::avecId(Builder::hero(), 2)]);

        self::assertTrue($rapport->estValide(), implode(' ', $rapport->erreurs));
    }

    public function testLimitesDeTaille(): void
    {
        $mission = Builder::mission(['min' => 2, 'max' => 3]);
        $v = $this->validator();

        self::assertStringContainsString('trop petite', $v->valider($mission, [self::avecId(Builder::hero(), 1)])->erreurs[0]);
        $quatre = array_map(fn (int $i) => self::avecId(Builder::hero(), $i), [1, 2, 3, 4]);
        self::assertStringContainsString('trop grande', $v->valider($mission, $quatre)->erreurs[0]);
    }

    public function testDoublonRefuse(): void
    {
        $hero = self::avecId(Builder::hero(), 1);
        $rapport = $this->validator()->valider(Builder::mission(), [$hero, $hero]);

        self::assertContains('Un même héros ne peut occuper deux emplacements.', $rapport->erreurs);
    }

    public function testHerosIndisponible(): void
    {
        $hero = self::avecId(Builder::hero(['nom' => 'Aura', 'statut' => HeroStatut::BLESSE]), 1);
        $rapport = $this->validator()->valider(Builder::mission(), [$hero]);

        self::assertFalse($rapport->estValide());
        self::assertStringContainsString('Aura est indisponible', $rapport->erreurs[0]);
    }

    public function testHerosDejaEngageDansUneAutreMissionActive(): void
    {
        $hero = self::avecId(Builder::hero(['nom' => 'Titan']), 7);
        $autre = Builder::mission(['nom' => 'Réacteur', 'statut' => MissionStatut::EN_COURS]);
        $rapport = $this->validator([new AffectationMission($hero, $autre)])->valider(Builder::mission(), [$hero]);

        self::assertStringContainsString('Titan est déjà engagé dans la mission « Réacteur »', $rapport->erreurs[0]);
    }

    public function testSanteSousLeSeuil(): void
    {
        $hero = self::avecId(Builder::hero(['nom' => 'Echo', 'pv' => 20]), 1);
        $rapport = $this->validator()->valider(Builder::mission(['seuil' => 30]), [$hero]);

        self::assertStringContainsString('santé de 20 %', $rapport->erreurs[0]);
    }

    public function testExigencesObligatoires(): void
    {
        $mission = Builder::mission(['combatRequis' => 85]);
        $mission->addAptitudeRequise(Builder::aptitude('IMMUNITE_ENERGIE'));
        $rapport = $this->validator()->valider($mission, [self::avecId(Builder::hero(['combat' => 80]), 1)]);

        self::assertCount(2, $rapport->erreurs);
        self::assertStringContainsString('Aptitude obligatoire manquante', implode(' ', $rapport->erreurs));
        self::assertStringContainsString('Combat ≥ 85', implode(' ', $rapport->erreurs));
    }

    public function testMissionCloseOuDejaDeployee(): void
    {
        $hero = self::avecId(Builder::hero(), 1);
        foreach ([MissionStatut::TERMINEE, MissionStatut::ANNULEE, MissionStatut::EN_COURS] as $statut) {
            self::assertFalse($this->validator()->valider(Builder::mission(['statut' => $statut]), [$hero])->estValide(), $statut->value);
        }
    }

    public function testAvertissementsNonBloquants(): void
    {
        $hero = self::avecId(Builder::hero(['fatigue' => 80, 'x' => 99.0]), 1);
        $rapport = $this->validator()->valider(Builder::mission(['x' => 50.0]), [$hero]);

        self::assertTrue($rapport->estValide());
        self::assertCount(2, $rapport->avertissements);
    }
}
