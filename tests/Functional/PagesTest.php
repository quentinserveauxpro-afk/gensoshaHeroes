<?php

namespace App\Tests\Functional;

use App\Entity\ObjectifMission;
use App\Enum\MissionStatut;
use App\Tests\Support\Builder;

final class PagesTest extends AppWebTestCase
{
    public function testPagesPrincipales(): void
    {
        $hero = Builder::hero(['nom' => 'Apex']);
        $mission = Builder::mission(['nom' => 'Opération Tempête d\'Acier', 'statut' => MissionStatut::EN_ATTENTE]);
        $mission->addObjectif((new ObjectifMission())->setTitre('Évacuer civils des quais')->setProgression(100));
        self::persister($this->em, $hero, $mission);
        $this->connecter('ROLE_OPERATOR');

        foreach ([
            '/' => 'Centre Tactique',
            '/squad-builder/'.$mission->getId() => 'Formation de l\'Unité Opérationnelle',
            '/heros' => 'Registre Opérationnel des Héros',
            '/heros/'.$hero->getId() => 'Profil Statistique',
            '/missions' => 'Missions',
            '/missions/'.$mission->getId() => 'Objectifs',
            '/requetes' => 'Saisie Opérateur',
        ] as $url => $texte) {
            $crawler = $this->client->request('GET', $url);
            self::assertResponseIsSuccessful($url);
            self::assertStringContainsString($texte, $crawler->filter('main')->text(), $url);
        }
    }

    public function testCentreTactiqueAfficheLesDonneesDoctrine(): void
    {
        $mission = Builder::mission(['nom' => 'Attaque du Réacteur Magna', 'statut' => MissionStatut::EN_ATTENTE, 'x' => 66.0, 'y' => 45.0]);
        $mission->addObjectif((new ObjectifMission())->setTitre('Couper l\'alimentation')->setProgression(80));
        $hero = Builder::hero(['nom' => 'Chronos', 'x' => 63.0, 'y' => 41.0]);
        self::persister($this->em, $mission, $hero);
        $this->connecter('ROLE_OPERATOR');

        $crawler = $this->client->request('GET', '/?mission='.$mission->getId());
        self::assertSelectorTextContains('.mission-card__title', 'Attaque du Réacteur Magna');
        self::assertSelectorTextContains('.map-hud', 'Couper l\'alimentation');
        self::assertCount(1, $crawler->filter('a.map-marker[aria-label="Chronos, à 5 km de la mission sélectionnée"]'));
        // Un timer sur la carte de mission et un dans le HUD, valeur de référence fournie par le serveur.
        self::assertCount(2, $crawler->filter('[data-controller="countdown"][data-countdown-seconds-value]'));
    }

    public function testFiltresDuCentreTactique(): void
    {
        self::persister($this->em,
            Builder::mission(['nom' => 'Alpha', 'statut' => MissionStatut::EN_ATTENTE, 'difficulte' => \App\Enum\Difficulte::S]),
            Builder::mission(['nom' => 'Beta', 'statut' => MissionStatut::EN_ATTENTE, 'difficulte' => \App\Enum\Difficulte::D]),
        );
        $this->connecter('ROLE_OPERATOR');

        $crawler = $this->client->request('GET', '/?difficulte=S');
        self::assertSame(['Alpha'], $crawler->filter('.mission-card__title')->each(static fn ($n) => $n->text()));
    }

    public function testApiTimerNeRenvoieJamaisDeDureeNegative(): void
    {
        $mission = Builder::mission(['fin' => new \DateTimeImmutable('-1 hour')]);
        self::persister($this->em, $mission);
        $this->connecter('ROLE_OPERATOR');

        $this->client->request('GET', '/api/missions/'.$mission->getId().'/timer');
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame(0, $data['secondesRestantes']);
        self::assertSame('Mission terminée', $data['libelle']);
    }

    public function testPaginationDuRoster(): void
    {
        for ($i = 1; $i <= 7; ++$i) {
            $this->em->persist(Builder::hero(['nom' => 'H'.$i]));
        }
        $this->em->flush();
        $this->connecter('ROLE_OPERATOR');

        $crawler = $this->client->request('GET', '/heros?page=2');
        self::assertCount(2, $crawler->filter('a.hero-row'));
        self::assertSelectorTextContains('.pager', '2 / 2');
    }
}
