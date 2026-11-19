<?php

namespace App\Tests\Functional;

use App\Entity\AffectationMission;
use App\Entity\Hero;
use App\Entity\Mission;
use App\Enum\HeroStatut;
use App\Enum\MissionStatut;
use App\Tests\Support\Builder;

final class SquadBuilderTest extends AppWebTestCase
{
    private function url(Mission $mission, array $heros): string
    {
        return '/squad-builder/'.$mission->getId().'?equipe='.implode(',', array_map(static fn (Hero $h) => $h->getId(), $heros));
    }

    public function testAjoutEtRetraitDUnHerosRecalculentLePronostic(): void
    {
        $mission = Builder::mission(['min' => 2, 'max' => 3]);
        [$a, $b] = [Builder::hero(['nom' => 'Volt']), Builder::hero(['nom' => 'Nova'])];
        self::persister($this->em, $mission, $a, $b);
        $this->connecter('ROLE_OPERATOR');

        $crawler = $this->client->request('GET', '/squad-builder/'.$mission->getId());
        self::assertSelectorTextContains('.ring__label', '0%');
        self::assertSelectorTextContains('.warnings', 'Équipe trop petite');

        // Ajout via le bouton de la réserve
        $crawler = $this->client->click($crawler->filter('a:contains("Assigner à l\'escouade")')->first()->link());
        self::assertCount(1, $crawler->filter('.slot:not(.slot--empty)'));
        $tauxUn = (int) $crawler->filter('.sb-layout .ring__label')->text();

        $crawler = $this->client->request('GET', $this->url($mission, [$a, $b]));
        self::assertCount(2, $crawler->filter('.slot:not(.slot--empty)'));
        self::assertGreaterThan($tauxUn, (int) $crawler->filter('.sb-layout .ring__label')->text());
        self::assertSelectorTextContains('.deploy-bar', 'Protocole de largage prêt');

        // Retrait
        $crawler = $this->client->click($crawler->filter('a[aria-label="Retirer Volt de l\'escouade"]')->link());
        self::assertSame(['Nova'], $crawler->filter('.slot__name')->each(static fn ($n) => $n->text()));
    }

    public function testDeploiementValideRedirigeVersLeCentreTactique(): void
    {
        $mission = Builder::mission(['nom' => 'Neutralisation du Titan d\'Or', 'min' => 1, 'max' => 2]);
        $hero = Builder::hero(['nom' => 'Volt']);
        self::persister($this->em, $mission, $hero);
        $this->connecter('ROLE_OPERATOR');

        $this->client->request('GET', $this->url($mission, [$hero]));
        $this->client->submitForm('Confirmer l\'équipe et déployer');
        self::assertResponseRedirects('/?mission='.$mission->getId());
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash--success', 'Équipe déployée sur « Neutralisation du Titan d\'Or »');

        $this->em->clear();
        self::assertSame(MissionStatut::EN_COURS, $this->em->find(Mission::class, $mission->getId())->getStatut());
        self::assertSame(HeroStatut::EN_MISSION, $this->em->find(Hero::class, $hero->getId())->getStatut());
        self::assertSame(1, $this->em->getRepository(AffectationMission::class)->count([]));
    }

    public function testValidationServeurMalgreUnFormulaireForge(): void
    {
        $mission = Builder::mission(['min' => 2, 'max' => 2]);
        $hero = Builder::hero(['statut' => HeroStatut::INDISPONIBLE]);
        self::persister($this->em, $mission, $hero);
        $this->connecter('ROLE_OPERATOR');

        $crawler = $this->client->request('GET', $this->url($mission, [$hero]));
        $form = $crawler->selectButton('Confirmer l\'équipe et déployer')->form();
        $form['equipe'] = $hero->getId().',999999';
        $this->client->submit($form);
        $this->client->followRedirect();

        self::assertSelectorExists('.flash--danger');
        self::assertSame(0, $this->em->getRepository(AffectationMission::class)->count([]));
    }

    public function testCompetenceObligatoireManquanteBloqueLeDeploiement(): void
    {
        $immunite = Builder::aptitude('IMMUNITE_ENERGIE');
        $mission = Builder::mission();
        $mission->addAptitudeRequise($immunite);
        $hero = Builder::hero();
        self::persister($this->em, $immunite, $mission, $hero);
        $this->connecter('ROLE_OPERATOR');

        $this->client->request('GET', $this->url($mission, [$hero]));
        self::assertAnySelectorTextContains('.warnings li', 'Aptitude obligatoire manquante : Immunite_energie.');
        $this->client->submitForm('Confirmer l\'équipe et déployer');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash--danger', 'Aptitude obligatoire manquante');
    }

    public function testFiltresDeLaReserveEtSuggestion(): void
    {
        $mission = Builder::mission(['x' => 50.0, 'y' => 50.0, 'min' => 1, 'max' => 2]);
        self::persister($this->em, $mission,
            Builder::hero(['nom' => 'Proche', 'x' => 51.0, 'y' => 50.0]),
            Builder::hero(['nom' => 'Lointain', 'x' => 90.0, 'y' => 90.0]),
            Builder::hero(['nom' => 'Blesse', 'statut' => HeroStatut::BLESSE]),
        );
        $this->connecter('ROLE_OPERATOR');

        $crawler = $this->client->request('GET', '/squad-builder/'.$mission->getId().'?filtre=proximite');
        $noms = $crawler->filter('.reserve-card strong.headline-sm')->each(static fn ($n) => $n->text());
        self::assertContains('Proche', $noms);
        self::assertNotContains('Lointain', $noms);

        $crawler = $this->client->request('GET', '/squad-builder/'.$mission->getId().'?filtre=disponibles');
        self::assertNotContains('Blesse', $crawler->filter('.reserve-card strong.headline-sm')->each(static fn ($n) => $n->text()));
        self::assertSelectorTextContains('.slot--empty', 'Suggestion : Proche');
    }
}
