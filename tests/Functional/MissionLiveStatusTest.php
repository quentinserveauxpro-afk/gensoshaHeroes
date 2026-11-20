<?php

namespace App\Tests\Functional;

use App\Entity\AffectationMission;
use App\Entity\Alerte;
use App\Entity\Hero;
use App\Entity\Mission;
use App\Entity\MissionSimulationState;
use App\Entity\ObjectifMission;
use App\Entity\User;
use App\Enum\MissionStatut;
use App\Enum\NiveauAlerte;
use App\Tests\Support\Builder;

/** Routes JSON du rafraîchissement automatique des missions. */
final class MissionLiveStatusTest extends AppWebTestCase
{
    /** Mission en cours avec deux objectifs (dont un atteint), une équipe et une alerte. */
    private function mission(array $o = [], int $heros = 1): Mission
    {
        $mission = Builder::mission($o + ['statut' => MissionStatut::EN_COURS, 'fin' => new \DateTimeImmutable('+600 seconds')]);
        $mission->setDescription('Description confidentielle du dossier');
        $mission->addObjectif((new ObjectifMission())->setTitre('Sécuriser le périmètre')->setProgression(100));
        $mission->addObjectif((new ObjectifMission())->setTitre('Neutraliser la menace')->setProgression(40));
        self::persister($this->em, $mission);
        for ($i = 0; $i < $heros; ++$i) {
            $hero = Builder::hero(['pv' => 72, 'pvMax' => 100, 'fatigue' => 34]);
            self::persister($this->em, $hero, new AffectationMission($hero, $mission, new \DateTimeImmutable('-1 minute')));
        }
        self::persister($this->em, (new Alerte('Renforts demandés', 'détail', NiveauAlerte::CRITIQUE, 'TEST-'.$mission->getId()))->setMission($mission));

        return $mission;
    }

    /** @return array<string, mixed> */
    private function json(string $url): array
    {
        $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();

        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    public function testStructureEtValeurs(): void
    {
        $mission = $this->mission();
        $this->em->clear();
        $this->connecter('ROLE_MISSION_OPERATOR');

        $etat = $this->json('/missions/live-status?ids='.$mission->getId());
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertNotFalse(\DateTimeImmutable::createFromFormat(\DATE_ATOM, $etat['serverTime']));
        self::assertCount(1, $etat['missions']);
        $m = $etat['missions'][0];
        foreach (['id', 'status', 'progress', 'successRate', 'remainingSeconds', 'updatedAt', 'objectives', 'heroes', 'deadline', 'alerts'] as $cle) {
            self::assertArrayHasKey($cle, $m);
        }
        self::assertSame('EN_COURS', $m['status']);
        self::assertFalse($m['closed']);
        self::assertGreaterThan(590, $m['remainingSeconds']);
        self::assertLessThanOrEqual(600, $m['remainingSeconds']);

        // Objectifs : un atteint, un en cours ; progression globale = moyenne des obligatoires.
        self::assertSame([[100, true], [40, false]], array_map(static fn ($o) => [$o['progress'], $o['completed']], $m['objectives']));
        self::assertSame(70, $m['progress']);

        self::assertSame(['id', 'name', 'health', 'maxHealth', 'healthPercent', 'fatigue', 'critical'], array_keys($m['heroes'][0]));
        self::assertSame([72, 100, 34, false], [$m['heroes'][0]['health'], $m['heroes'][0]['maxHealth'], $m['heroes'][0]['fatigue'], $m['heroes'][0]['critical']]);
        self::assertSame('Renforts demandés', $m['alerts'][0]['title']);
    }

    public function testAucuneDonneeSensible(): void
    {
        $mission = $this->mission();
        $this->connecter('ROLE_HERO_OPERATOR');
        $this->client->request('GET', '/missions/live-status?ids='.$mission->getId());
        $brut = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('Description confidentielle', $brut);
        foreach (['description', 'image', 'email', 'password', 'stats', 'detail'] as $champ) {
            self::assertStringNotContainsString('"'.$champ.'"', $brut);
        }
        self::assertStringNotContainsString('détail', $brut, 'message des alertes non diffusé');
    }

    public function testValeursBorneesEntre0Et100(): void
    {
        $mission = $this->mission();
        $mission->setTauxDeReussite(140);
        $objectif = $mission->getObjectifs()->last();
        $etat = (new MissionSimulationState($mission, new \DateTimeImmutable()))->setProgressionPrecise($objectif->getId(), 180.0);
        $hero = $this->em->getRepository(AffectationMission::class)->findOneBy(['mission' => $mission])->getHero()->setFatigue(100)->setPointsDeVie(0);
        self::persister($this->em, $etat, $hero);
        $this->connecter();

        $m = $this->json('/missions/live-status?ids='.$mission->getId())['missions'][0];
        self::assertSame(100, $m['successRate']);
        self::assertSame(100, $m['objectives'][1]['progress']);
        self::assertSame(100, $m['progress']);
        self::assertSame(0, $m['heroes'][0]['healthPercent']);
        self::assertSame(100, $m['heroes'][0]['fatigue']);
        self::assertTrue($m['heroes'][0]['critical']);
        foreach ([$m['progress'], $m['successRate'], ...array_column($m['objectives'], 'progress')] as $v) {
            self::assertGreaterThanOrEqual(0, $v);
            self::assertLessThanOrEqual(100, $v);
        }
    }

    public function testTempsRestantJamaisNegatifEtMissionTerminee(): void
    {
        $echue = $this->mission(['fin' => new \DateTimeImmutable('-5 minutes')]);
        $terminee = $this->mission(['statut' => MissionStatut::TERMINEE, 'fin' => new \DateTimeImmutable('+1 hour')]);
        $this->connecter();

        $missions = array_column($this->json(sprintf('/missions/live-status?ids=%d,%d', $echue->getId(), $terminee->getId()))['missions'], null, 'id');
        self::assertSame(0, $missions[$echue->getId()]['remainingSeconds']);
        self::assertFalse($missions[$echue->getId()]['closed'], 'le navigateur ne décide pas de la fin');
        self::assertSame(0, $missions[$terminee->getId()]['remainingSeconds']);
        self::assertTrue($missions[$terminee->getId()]['closed']);
        self::assertSame('Terminée', $missions[$terminee->getId()]['statusLabel']);
    }

    public function testPlusieursMissionsEtIdentifiantsFiltres(): void
    {
        $a = $this->mission();
        $b = $this->mission();
        $this->connecter();

        $ids = array_column($this->json(sprintf('/missions/live-status?ids=%d,abc,-3,%d,%d,999999,1e3', $a->getId(), $b->getId(), $a->getId()))['missions'], 'id');
        self::assertSame([$a->getId(), $b->getId()], $ids, 'une entrée par mission existante, aucun doublon');
        self::assertSame([], $this->json('/missions/live-status')['missions']);
        self::assertSame([], $this->json('/missions/live-status?ids='.str_repeat('9', 40))['missions']);
    }

    public function testRouteCiblee(): void
    {
        $mission = $this->mission();
        $this->connecter('ROLE_HERO_OPERATOR');
        $etat = $this->json('/missions/'.$mission->getId().'/live-status');
        self::assertSame($mission->getId(), $etat['missions'][0]['id']);
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));

        $this->client->request('GET', '/missions/999999/live-status');
        self::assertResponseStatusCodeSame(404);
    }

    public function testAccesRefuse(): void
    {
        $mission = $this->mission();
        $this->client->request('GET', '/missions/live-status?ids='.$mission->getId());
        self::assertResponseRedirects(); // anonyme : vers la connexion
        $this->client->request('GET', '/missions/'.$mission->getId().'/live-status');
        self::assertResponseRedirects();

        // Compte sans rôle du personnel : refusé.
        $user = (new User())->setEmail('citoyen@test.local')->setNom('Citoyen')->setRoles([])->setPassword('x');
        self::persister($this->em, $user);
        $this->client->loginUser($user);
        $this->client->request('GET', '/missions/live-status?ids='.$mission->getId());
        self::assertResponseStatusCodeSame(403);
    }

    public function testNombreDeRequetesConstantSansN1(): void
    {
        $une = $this->mission(heros: 1);
        $six = [];
        for ($i = 0; $i < 6; ++$i) {
            $six[] = $this->mission(heros: 3);
        }
        $this->connecter();
        $this->client->request('GET', '/missions/live-status'); // préchauffage : les insertions du test ne sont pas comptées
        $compter = function (array $missions): int {
            $this->em->clear();
            $this->client->enableProfiler();
            $this->client->request('GET', '/missions/live-status?ids='.implode(',', array_map(static fn (Mission $m) => $m->getId(), $missions)));
            self::assertResponseIsSuccessful();

            return $this->client->getProfile()->getCollector('db')->getQueryCount();
        };

        $pourUne = $compter([$une]);
        self::assertSame($pourUne, $compter($six), 'même nombre de requêtes pour 1 ou 6 missions (18 héros)');
        self::assertLessThanOrEqual(4, $pourUne, 'utilisateur, missions + relations, états de simulation, alertes');
    }

    public function testPageMissionsSansN1(): void
    {
        $this->connecter();
        $this->client->request('GET', '/missions/live-status');
        $compter = function (): int {
            $this->em->clear();
            $this->client->enableProfiler();
            $this->client->request('GET', '/missions');
            self::assertResponseIsSuccessful();

            return $this->client->getProfile()->getCollector('db')->getQueryCount();
        };
        $this->mission(heros: 3);
        $this->client->request('GET', '/missions/live-status');
        $pourUne = $compter();
        for ($i = 0; $i < 5; ++$i) {
            $this->mission(heros: 3);
        }
        $this->client->request('GET', '/missions/live-status');
        self::assertSame($pourUne, $compter(), 'relations de la page chargées en une requête groupée');
    }

    public function testPageMissionsEtCentreTactiqueInstrumentes(): void
    {
        $mission = $this->mission(['nom' => 'Raid Lyon']);
        $this->connecter();

        $crawler = $this->client->request('GET', '/missions');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('[data-controller~="mission-status"]'));
        $ligne = $crawler->filter('[data-mission-id="'.$mission->getId().'"]');
        self::assertSame('Raid Lyon', $ligne->attr('data-mission-name'));
        self::assertSame('70', $ligne->filter('[data-mission-progress]')->attr('data-mission-progress'));
        self::assertCount(1, $ligne->filter('[data-mission-progress] [role="progressbar"][aria-valuemin="0"][aria-valuemax="100"][aria-valuenow="70"]'));
        self::assertCount(1, $ligne->filter('[data-mission-timer][data-countdown-seconds-value]'));
        self::assertCount(1, $ligne->filter('[data-hero-id][data-hero-health="72"][data-hero-fatigue="34"]'));

        $crawler = $this->client->request('GET', '/?mission='.$mission->getId());
        self::assertResponseIsSuccessful();
        $racine = $crawler->filter('[data-controller~="mission-status"]');
        self::assertCount(1, $racine, 'une seule boucle de rafraîchissement par page');
        self::assertSame((string) $mission->getId(), $racine->attr('data-mission-status-selection-value'));
        self::assertGreaterThanOrEqual(2, $crawler->filter('[data-mission-id="'.$mission->getId().'"]')->count(), 'carte de la liste + fiche sur la carte');
        self::assertCount(2, $crawler->filter('.card--hud [data-objective-id]'));
    }
}
