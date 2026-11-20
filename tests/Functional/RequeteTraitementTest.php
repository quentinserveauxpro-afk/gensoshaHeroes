<?php

namespace App\Tests\Functional;

use App\Entity\GeoPosition;
use App\Entity\Mission;
use App\Entity\RequeteCitoyenne;
use App\Enum\CategorieIncident;
use App\Enum\NiveauUrgence;
use App\Enum\OrigineAlerte;
use App\Enum\RequeteStatut;
use App\Enum\StatutTraitement;

final class RequeteTraitementTest extends AppWebTestCase
{
    private function requete(array $o = []): RequeteCitoyenne
    {
        $r = (new RequeteCitoyenne())
            ->setTitre($o['titre'] ?? 'Fuite de gaz rue Victor Hugo')
            ->setDescription('Forte odeur de gaz dans tout l\'immeuble, habitants évacués.')
            ->setAdresse('5 rue Victor Hugo')->setCodePostal('69002')->setVille('Lyon')->setSecteur('69 · Rhône')
            ->setPositionX(50.0)->setPositionY(50.0)
            ->setNiveauUrgence($o['urgence'] ?? NiveauUrgence::MOYENNE)
            ->setCategorieIncident($o['categorie'] ?? CategorieIncident::CATASTROPHE)
            ->setOrigine(OrigineAlerte::CIVIL)->setNomDemandeur('Anonyme')
            ->setDateCreation(new \DateTimeImmutable('-10 minutes'))
            ->setStatutTraitement($o['statut'] ?? StatutTraitement::NOUVELLE);
        $r->setGeoPosition(GeoPosition::en(45.7600, 4.8350));
        self::persister($this->em, $r);

        return $r;
    }

    public function testOperateurHerosNeTraitePasLesRequetes(): void
    {
        $r = $this->requete();
        $this->connecter('ROLE_HERO_OPERATOR');
        foreach (['/requetes', '/requetes/'.$r->getId()] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseStatusCodeSame(403, $url);
        }
        $this->client->request('POST', '/requetes/'.$r->getId().'/convertir');
        self::assertResponseStatusCodeSame(403);
    }

    public function testFiltresParUrgenceCategorieSecteurEtStatut(): void
    {
        $this->requete(['titre' => 'Alpha critique', 'urgence' => NiveauUrgence::CRITIQUE]);
        $this->requete(['titre' => 'Bravo animal', 'categorie' => CategorieIncident::ASSISTANCE_ANIMALE]);
        $this->requete(['titre' => 'Charlie rejetée', 'statut' => StatutTraitement::REJETEE]);
        $this->connecter('ROLE_MISSION_OPERATOR');

        $texte = fn (array $q) => $this->client->request('GET', '/requetes?'.http_build_query($q))->filter('main')->text();
        self::assertStringContainsString('Alpha critique', $texte(['urgence' => 'CRITIQUE']));
        self::assertStringNotContainsString('Bravo animal', $texte(['urgence' => 'CRITIQUE']));
        self::assertStringContainsString('Bravo animal', $texte(['type' => 'ASSISTANCE_ANIMALE']));
        self::assertStringNotContainsString('Alpha critique', $texte(['type' => 'ASSISTANCE_ANIMALE']));
        self::assertStringContainsString('Charlie rejetée', $texte(['statut' => 'REJETEE']));
        self::assertStringNotContainsString('Alpha critique', $texte(['statut' => 'REJETEE']));
        self::assertStringContainsString('Alpha critique', $texte(['secteur' => '69 · Rhône']));
        self::assertStringNotContainsString('Alpha critique', $texte(['secteur' => '75 · Paris']));
    }

    public function testQualificationHistoriseeEtUrgenceInterne(): void
    {
        $r = $this->requete();
        $operateur = $this->connecter('ROLE_MISSION_OPERATOR');

        $crawler = $this->client->request('GET', '/requetes/'.$r->getId());
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Enregistrer la qualification')->form([
            'qualification_requete[statut]' => StatutTraitement::VALIDEE->value,
            'qualification_requete[urgenceInterne]' => NiveauUrgence::CRITIQUE->value,
            'qualification_requete[commentaire]' => 'Confirmé par les pompiers',
        ]);
        $this->client->submit($form);
        self::assertResponseRedirects('/requetes/'.$r->getId());

        $this->em->clear();
        $r = $this->em->find(RequeteCitoyenne::class, $r->getId());
        self::assertSame(StatutTraitement::VALIDEE, $r->getStatutTraitement());
        self::assertSame(RequeteStatut::EN_QUALIFICATION, $r->getStatut(), 'Statut historique synchronisé.');
        self::assertSame(NiveauUrgence::CRITIQUE, $r->getNiveauUrgence());
        self::assertFalse($r->isUrgenceAQualifier());
        self::assertSame($operateur->getId(), $r->getTraiteePar()?->getId());
        self::assertNotNull($r->getDateDernierTraitement());
        $entree = $r->getHistorique()->last();
        self::assertSame('QUALIFICATION', $entree->getAction());
        self::assertStringContainsString('Confirmé par les pompiers', (string) $entree->getCommentaire());
    }

    public function testRejetExigeUnMotif(): void
    {
        $r = $this->requete();
        $this->connecter('ROLE_MISSION_OPERATOR');
        $crawler = $this->client->request('GET', '/requetes/'.$r->getId());
        $jeton = $crawler->filter('form[action$="/rejeter"] input[name="_token"]')->attr('value');

        $this->client->request('POST', '/requetes/'.$r->getId().'/rejeter', ['_token' => $jeton, 'motif' => '']);
        $this->em->clear();
        self::assertSame(StatutTraitement::NOUVELLE, $this->em->find(RequeteCitoyenne::class, $r->getId())->getStatutTraitement());

        $this->client->request('POST', '/requetes/'.$r->getId().'/rejeter', ['_token' => $jeton, 'motif' => 'Doublon de REQ-0001']);
        $this->em->clear();
        $r = $this->em->find(RequeteCitoyenne::class, $r->getId());
        self::assertSame(StatutTraitement::REJETEE, $r->getStatutTraitement());
        self::assertSame('Doublon de REQ-0001', $r->getMotifRejet());
    }

    public function testConversionTransactionnelleVersMissionGeolocalisee(): void
    {
        $r = $this->requete(['urgence' => NiveauUrgence::HAUTE]);
        $operateur = $this->connecter('ROLE_MISSION_OPERATOR');

        $crawler = $this->client->request('GET', '/requetes/'.$r->getId());
        $this->client->submit($crawler->selectButton('Convertir en mission officielle')->form());
        $mission = $this->em->getRepository(Mission::class)->findOneBy([]);
        self::assertNotNull($mission);
        self::assertResponseRedirects('/missions/'.$mission->getId());

        $this->em->clear();
        $r = $this->em->find(RequeteCitoyenne::class, $r->getId());
        $mission = $this->em->find(Mission::class, $mission->getId());
        self::assertSame(StatutTraitement::CONVERTIE_EN_MISSION, $r->getStatutTraitement());
        self::assertSame(RequeteStatut::CONVERTIE, $r->getStatut());
        self::assertSame($mission->getId(), $r->getMission()?->getId());
        self::assertSame($operateur->getId(), $r->getConvertiePar()?->getId());
        self::assertNotNull($r->getDateConversion());
        self::assertSame('Fuite de gaz rue Victor Hugo', $mission->getNom());
        self::assertSame(NiveauUrgence::HAUTE, $mission->getNiveauUrgence());
        self::assertTrue($mission->isGeolocalisee());
        self::assertEqualsWithDelta(45.76, $mission->getCoordonnees()->latitude, 1e-6);
        self::assertNotSame($r->getGeoPosition()->getId(), $mission->getGeoPosition()->getId(), 'Position copiée, non partagée.');

        $this->client->request('POST', '/requetes/'.$r->getId().'/convertir');
        self::assertResponseStatusCodeSame(403, 'Une requête convertie ne peut plus l\'être.');
    }

    public function testOperateurHistoriqueConserveSesDroits(): void
    {
        $this->requete();
        $this->connecter('ROLE_OPERATOR');
        $this->client->request('GET', '/requetes');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[action$="/classer"]');
        self::assertSelectorNotExists('form[action$="/convertir"]');
    }
}
