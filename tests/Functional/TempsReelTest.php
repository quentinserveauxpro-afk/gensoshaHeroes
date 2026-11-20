<?php

namespace App\Tests\Functional;

use App\Entity\AffectationMission;
use App\Entity\GeoPosition;
use App\Enum\MissionStatut;
use App\Tests\Support\Builder;

final class TempsReelTest extends AppWebTestCase
{
    private function scenario(): void
    {
        $mission = Builder::mission(['nom' => 'Raid Lyon', 'statut' => MissionStatut::EN_COURS, 'fin' => new \DateTimeImmutable('+2 hours')]);
        $mission->setGeoPosition(GeoPosition::en(45.7640, 4.8357))->setLocalisation('Lyon')->setDateDebut(new \DateTimeImmutable('-1 minute'));
        $mission->addVillain(Builder::villain(6));
        $hero = Builder::hero(['nom' => 'Valkyrie']);
        $hero->setGeoPosition(GeoPosition::en(48.8566, 2.3522));
        self::persister($this->em, $mission, $hero, ...$mission->getVillains());
        self::persister($this->em, new AffectationMission($hero, $mission, new \DateTimeImmutable('-1 minute')));
        $this->em->clear();
    }

    public function testInstantaneJsonAgrege(): void
    {
        $this->scenario();
        $this->connecter('ROLE_HERO_OPERATOR');
        $this->client->request('GET', '/api/temps-reel/etat');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));

        $etat = json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('genereLe', $etat);
        self::assertStringContainsString('ligne droite', $etat['trajet']);
        self::assertSame('Raid Lyon', $etat['missions'][0]['nom']);
        self::assertSame(['lat' => 45.764, 'lng' => 4.8357], $etat['missions'][0]['position']);
        self::assertSame('APPROCHE', $etat['missions'][0]['phase']);
        self::assertCount(1, $etat['missions'][0]['vilains']);
        $valkyrie = array_values(array_filter($etat['heros'], static fn (array $h) => 'Valkyrie' === $h['nom']))[0];
        self::assertSame($etat['missions'][0]['id'], $valkyrie['missionId']);
        self::assertEqualsWithDelta(391.5, $valkyrie['distanceMissionKm'], 1.5);
        self::assertArrayHasKey('sante', $valkyrie);
    }

    public function testFluxServerSentEvents(): void
    {
        $this->scenario();
        $this->connecter('ROLE_MISSION_OPERATOR');
        $this->client->request('GET', '/api/temps-reel/flux');
        self::assertResponseIsSuccessful();
        self::assertStringStartsWith('text/event-stream', (string) $this->client->getResponse()->headers->get('Content-Type'));
        self::assertResponseHeaderSame('X-Accel-Buffering', 'no');
        $contenu = (string) $this->client->getInternalResponse()->getContent();
        self::assertStringContainsString('retry: 3000', $contenu);
        self::assertStringContainsString("event: etat\n", $contenu);
        self::assertStringContainsString('Raid Lyon', $contenu);
    }

    public function testCarteReelleSurLeCentreTactique(): void
    {
        $this->scenario();
        $this->connecter('ROLE_MISSION_OPERATOR');
        $crawler = $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        $carte = $crawler->filter('[data-controller="geo-map"]');
        self::assertCount(1, $carte);
        $etat = json_decode((string) $carte->attr('data-geo-map-etat-value'), true);
        self::assertSame('Raid Lyon', $etat['missions'][0]['nom']);
        $config = json_decode((string) $carte->attr('data-geo-map-config-value'), true);
        self::assertStringContainsString('openstreetmap', $config['tuiles']);
        self::assertArrayHasKey('La Réunion', $config['outreMer']);
        self::assertSelectorExists('[data-controller="realtime"][data-realtime-flux-url-value="/api/temps-reel/flux"]');
        self::assertSelectorTextContains('.map-legend', 'ligne droite');
    }
}
