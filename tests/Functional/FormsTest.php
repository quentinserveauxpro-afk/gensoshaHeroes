<?php

namespace App\Tests\Functional;

use App\Entity\Alerte;
use App\Entity\Hero;
use App\Entity\Mission;
use App\Entity\RequeteCitoyenne;
use App\Enum\NiveauUrgence;
use App\Tests\Support\Builder;

final class FormsTest extends AppWebTestCase
{
    public function testCreationDUnHerosParFormulaire(): void
    {
        $this->connecter('ROLE_ADMIN');
        $this->client->request('GET', '/heros/nouveau');
        $this->client->submitForm('Enregistrer', [
            'hero[nom]' => 'Shadowstep', 'hero[alias]' => 'Kaï Moreau', 'hero[rang]' => 'BETA', 'hero[role]' => 'ECLAIREUR',
            'hero[niveau]' => 36, 'hero[xp]' => 100, 'hero[pointsDeVie]' => 260, 'hero[pointsDeVieMaximum]' => 300,
            'hero[combat]' => 74, 'hero[intelligence]' => 80, 'hero[charisme]' => 58, 'hero[mobilite]' => 94, 'hero[vigueur]' => 55,
            'hero[positionX]' => '32.5', 'hero[positionY]' => '70',
        ]);
        self::assertResponseRedirects();

        $hero = $this->em->getRepository(Hero::class)->findOneBy(['nom' => 'Shadowstep']);
        self::assertNotNull($hero);
        self::assertSame(94, $hero->getMobilite());
        self::assertSame(32.5, $hero->getPositionX());
    }

    public function testValidationDuFormulaireHeros(): void
    {
        $this->connecter('ROLE_ADMIN');
        $this->client->request('GET', '/heros/nouveau');
        $this->client->submitForm('Enregistrer', [
            'hero[nom]' => '', 'hero[combat]' => 140, 'hero[pointsDeVie]' => 500, 'hero[pointsDeVieMaximum]' => 100,
        ]);
        self::assertResponseStatusCodeSame(422);
        $erreurs = $this->client->getCrawler()->filter('.form-errors li')->each(static fn ($n) => $n->text());
        self::assertGreaterThanOrEqual(3, \count($erreurs));
        self::assertContains('Les points de vie ne peuvent pas dépasser le maximum (100).', $erreurs);
        self::assertSame(0, $this->em->getRepository(Hero::class)->count([]));
    }

    public function testModificationDUnHeros(): void
    {
        $hero = Builder::hero(['nom' => 'Frost']);
        self::persister($this->em, $hero);
        $this->connecter('ROLE_ADMIN');

        $this->client->request('GET', '/heros/'.$hero->getId().'/modifier');
        $this->client->submitForm('Enregistrer', ['hero[vigueur]' => 77]);
        self::assertResponseRedirects('/heros/'.$hero->getId());
        $this->em->clear();
        self::assertSame(77, $this->em->find(Hero::class, $hero->getId())->getVigueur());
    }

    public function testCreationEtModificationDUneMission(): void
    {
        $this->connecter('ROLE_ADMIN');
        $this->client->request('GET', '/missions/nouvelle');
        $this->client->submitForm('Enregistrer', [
            'mission[nom]' => 'Incendie Chimique Docks Est', 'mission[description]' => 'Risque d\'explosion toxique',
            'mission[difficulte]' => 'S', 'mission[niveauUrgence]' => 'CRITIQUE',
            'mission[dateFinPrevue]' => (new \DateTimeImmutable('+1 hour'))->format('Y-m-d\TH:i'),
            'mission[tailleEquipeMinimale]' => 2, 'mission[tailleEquipeMaximale]' => 4,
        ]);
        self::assertResponseRedirects();
        $mission = $this->em->getRepository(Mission::class)->findOneBy(['nom' => 'Incendie Chimique Docks Est']);
        self::assertNotNull($mission);
        self::assertSame(1, $this->em->getRepository(Alerte::class)->count([]), 'Une mission critique génère une alerte.');

        $this->client->request('GET', '/missions/'.$mission->getId().'/modifier');
        $this->client->submitForm('Enregistrer', ['mission[tailleEquipeMinimale]' => 5, 'mission[tailleEquipeMaximale]' => 3]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.form-errors', 'La taille maximale doit être supérieure ou égale à la taille minimale.');
    }

    public function testEnregistrementDUneRequeteCitoyenneParUnOperateur(): void
    {
        $this->connecter('ROLE_OPERATOR');
        $this->client->request('GET', '/requetes');
        $this->client->submitForm('Publier l\'alerte réseau', [
            'requete_citoyenne[origine]' => 'POLICE',
            'requete_citoyenne[titre]' => 'Effondrement du Pont Saint-Martin',
            'requete_citoyenne[description]' => 'Bus scolaires suspendus',
            'requete_citoyenne[categorie]' => 'URGENCE_VITALE',
            'requete_citoyenne[adresse]' => 'Pylône Sud B-12',
            'requete_citoyenne[niveauUrgence]' => 'CRITIQUE',
            'requete_citoyenne[nomDemandeur]' => 'Police Centrale',
        ]);
        self::assertResponseRedirects('/requetes');

        $requete = $this->em->getRepository(RequeteCitoyenne::class)->findOneBy([]);
        self::assertSame(NiveauUrgence::CRITIQUE, $requete->getNiveauUrgence());
        self::assertSame(1, $this->em->getRepository(Alerte::class)->count(['requete' => $requete]));
    }

    public function testConversionDUneRequeteEnMissionDepuisLInterface(): void
    {
        $requete = (new RequeteCitoyenne())->setTitre('Chat géant mutant')->setDescription('Antenne T-8')->setAdresse('Tour Telecom')->setNomDemandeur('Riverain');
        self::persister($this->em, $requete);
        $this->connecter('ROLE_ADMIN');

        $this->client->request('GET', '/requetes');
        $this->client->submitForm('Convertir en mission officielle');
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Chat géant mutant');
        self::assertSelectorTextContains('main', 'Issue de la requête citoyenne '.$requete->getReference());
    }

    public function testAjoutDObjectif(): void
    {
        $mission = Builder::mission();
        self::persister($this->em, $mission);
        $this->connecter('ROLE_ADMIN');

        $this->client->request('GET', '/missions/'.$mission->getId());
        $this->client->submitForm('Ajouter', ['objectif_mission[titre]' => 'Sécuriser générateur', 'objectif_mission[progression]' => 40]);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.objectives', 'Sécuriser générateur');
    }
}
