<?php

namespace App\Tests\Unit\Movement;

use App\Entity\HeroMovement;
use App\Enum\MouvementStatut;
use App\Geo\Coordonnees;
use App\Geo\DistanceCalculator;
use App\Geo\EstimatedArrivalCalculator;
use App\Movement\HeroMovementService;
use App\Repository\HeroMovementRepository;
use App\Tests\Support\Builder;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/** Position en fonction du temps et détection d'arrivée (logique serveur pure). */
final class HeroMovementEtatTest extends TestCase
{
    private HeroMovementService $service;
    private DistanceCalculator $distances;

    protected function setUp(): void
    {
        $this->distances = new DistanceCalculator();
        $this->service = new HeroMovementService(
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(HeroMovementRepository::class),
            $this->distances,
            new EstimatedArrivalCalculator(1.0),
            new MockClock('2026-11-20 10:00:00'),
        );
    }

    private function mouvement(): HeroMovement
    {
        $paris = new Coordonnees(48.8566, 2.3522);
        $lyon = new Coordonnees(45.7640, 4.8357);

        return new HeroMovement(Builder::hero(), Builder::mission(), $paris, $lyon, $this->distances->distanceKm($paris, $lyon), 200.0,
            new \DateTimeImmutable('2026-11-20 10:00:00'), new \DateTimeImmutable('2026-11-20 12:00:00'));
    }

    public function testPositionAMiParcours(): void
    {
        $m = $this->mouvement();
        $etat = $this->service->etatA($m, new \DateTimeImmutable('2026-11-20 11:00:00'));

        self::assertFalse($etat->arrive);
        self::assertSame(50.0, $etat->progression());
        self::assertSame(3600, $etat->secondesRestantes);
        self::assertEqualsWithDelta($m->getDistanceKm() / 2, $etat->distanceRestanteKm, 0.01);
        self::assertEqualsWithDelta($m->getDistanceKm() / 2, $this->distances->distanceKm($m->getDepart(), $etat->position), 0.5);
    }

    public function testArriveeDetecteeApresLHeurePrevue(): void
    {
        $etat = $this->service->etatA($this->mouvement(), new \DateTimeImmutable('2026-11-20 12:00:01'));
        self::assertTrue($etat->arrive);
        self::assertSame(0, $etat->secondesRestantes);
        self::assertEqualsWithDelta(0.0, $etat->distanceRestanteKm, 1e-6);
    }

    public function testArriveeEnregistreeAvecHeureReelle(): void
    {
        $m = $this->mouvement();
        $m->arriver(new \DateTimeImmutable('2026-11-20 12:00:03'));

        self::assertSame(MouvementStatut::ARRIVE, $m->getStatut());
        self::assertEquals(new \DateTimeImmutable('2026-11-20 12:00:03'), $m->getDateArriveeReelle());
        self::assertFalse($m->isEnCours());
        self::assertTrue($m->getPositionActuelle()->egale($m->getArrivee(), 1e-6));
    }

    public function testDeplacementInterrompuNeBougePlus(): void
    {
        $m = $this->mouvement();
        $m->interrompre(new \DateTimeImmutable('2026-11-20 10:30:00'));
        $etat = $this->service->etatA($m, new \DateTimeImmutable('2026-11-20 11:30:00'));

        self::assertSame(MouvementStatut::INTERROMPU, $m->getStatut());
        self::assertFalse($etat->arrive);
    }
}
