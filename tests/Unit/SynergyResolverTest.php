<?php

namespace App\Tests\Unit;

use App\Entity\Synergie;
use App\Enum\Difficulte;
use App\Enum\Statistique;
use App\Service\Mission\SynergyResolver;
use App\Tests\Support\Builder;
use PHPUnit\Framework\TestCase;

final class SynergyResolverTest extends TestCase
{
    public function testSynergieActiveQuandTousLesHerosSontPresents(): void
    {
        [$a, $b, $c] = [Builder::hero(), Builder::hero(), Builder::hero()];
        $synergie = (new Synergie())->setNom('Duo')->setStatistique(Statistique::COMBAT)->setValeurBonus(10)->addHero($a)->addHero($b);

        $resolver = new SynergyResolver();
        self::assertSame([$synergie], $resolver->synergiesActives([$a, $b, $c]));
        self::assertSame([], $resolver->synergiesActives([$a, $c]));
    }

    public function testNombreMinimalDeHeros(): void
    {
        [$a, $b, $c] = [Builder::hero(), Builder::hero(), Builder::hero()];
        (new Synergie())->setNom('Trio')->setNombreMinimalHeros(2)->addHero($a)->addHero($b)->addHero($c);

        self::assertCount(1, (new SynergyResolver())->synergiesActives([$a, $c]));
    }

    public function testDifficulteMinimaleEtInactivite(): void
    {
        [$a, $b] = [Builder::hero(), Builder::hero()];
        $synergie = (new Synergie())->setNom('Elite')->setDifficulteMinimale(Difficulte::A)->addHero($a)->addHero($b);
        $resolver = new SynergyResolver();

        self::assertSame([], $resolver->synergiesActives([$a, $b], Builder::mission(['difficulte' => Difficulte::B])));
        self::assertCount(1, $resolver->synergiesActives([$a, $b], Builder::mission(['difficulte' => Difficulte::S])));

        $synergie->setActif(false);
        self::assertSame([], $resolver->synergiesActives([$a, $b], Builder::mission(['difficulte' => Difficulte::S])));
    }
}
