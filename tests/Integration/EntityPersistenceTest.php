<?php

namespace App\Tests\Integration;

use App\Entity\Hero;
use App\Entity\Mission;
use App\Entity\ObjectifMission;
use App\Enum\Difficulte;
use App\Enum\HeroStatut;
use App\Tests\Support\Builder;
use App\Tests\Support\ResetDatabaseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class EntityPersistenceTest extends KernelTestCase
{
    use ResetDatabaseTrait;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        self::viderBase($this->em);
    }

    public function testCreationEtModificationDUnHeros(): void
    {
        $hero = Builder::hero(['nom' => 'Frost', 'combat' => 70]);
        self::persister($this->em, $hero);
        self::assertNotNull($hero->getCreatedAt());

        $hero->setCombat(75)->setStatut(HeroStatut::REPOS);
        $this->em->flush();
        $this->em->clear();

        $recharge = $this->em->find(Hero::class, $hero->getId());
        self::assertSame(75, $recharge->getCombat());
        self::assertSame('75.00', $recharge->getStatCombat(), 'La colonne DECIMAL existante est conservée.');
        self::assertSame(HeroStatut::REPOS, $recharge->getStatut());
        self::assertGreaterThanOrEqual($recharge->getCreatedAt(), $recharge->getUpdatedAt());
    }

    public function testCreationDUneMissionAvecObjectifsOrdonnes(): void
    {
        $mission = Builder::mission(['difficulte' => Difficulte::S]);
        $mission->addObjectif((new ObjectifMission())->setTitre('Deux')->setOrdreAffichage(2));
        $mission->addObjectif((new ObjectifMission())->setTitre('Un')->setOrdreAffichage(1)->setProgression(100));
        $mission->addVillain(Builder::villain(8));
        self::persister($this->em, $mission, ...$mission->getVillains()->toArray());
        $this->em->clear();

        $recharge = $this->em->find(Mission::class, $mission->getId());
        self::assertSame(['Un', 'Deux'], $recharge->getObjectifs()->map(static fn ($o) => $o->getTitre())->toArray());
        self::assertTrue($recharge->getObjectifs()->first()->isTermine());
        self::assertSame(50, $recharge->getProgression());
        self::assertSame(8, $recharge->getNiveauMenaceMax());
        self::assertSame($recharge->getTimer(), $recharge->getDateFinPrevue());
        self::assertSame(Difficulte::S, $recharge->getDifficulte());
    }

    public function testContraintesDeValidation(): void
    {
        $validator = self::getContainer()->get(ValidatorInterface::class);

        $hero = Builder::hero(['combat' => 120, 'pv' => 150, 'pvMax' => 100]);
        $chemins = array_map(static fn ($v) => $v->getPropertyPath(), iterator_to_array($validator->validate($hero)));
        self::assertContains('combat', $chemins);
        self::assertContains('pointsDeVie', $chemins);

        $negatif = Builder::hero(['pv' => -1]);
        self::assertCount(1, $validator->validate($negatif));

        $mission = Builder::mission(['min' => 4, 'max' => 2])->setTauxDeReussite(140);
        $chemins = array_map(static fn ($v) => $v->getPropertyPath(), iterator_to_array($validator->validate($mission)));
        self::assertContains('tauxDeReussite', $chemins);
        self::assertContains('tailleEquipeMaximale', $chemins);

        self::assertCount(0, $validator->validate(Builder::hero()));
        self::assertCount(0, $validator->validate(Builder::mission()));
    }
}
