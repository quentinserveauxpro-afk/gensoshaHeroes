<?php

namespace App\Tests\Functional;

use App\Entity\AffectationMission;
use App\Entity\GeoPosition;
use App\Entity\Hero;
use App\Entity\SejourInfirmerie;
use App\Enum\HeroStatut;
use App\Enum\MotifInfirmerie;
use App\Enum\StatutSejour;
use App\Enum\TypeAdmission;
use App\Geo\Coordonnees;
use App\Tests\Support\Builder;
use PHPUnit\Framework\Attributes\DataProvider;

final class InfirmerieTest extends AppWebTestCase
{
    private function sejour(Hero $hero, MotifInfirmerie $motif = MotifInfirmerie::BLESSURE, StatutSejour $statut = StatutSejour::EN_TRAITEMENT): SejourInfirmerie
    {
        $now = new \DateTimeImmutable('-10 minutes');
        $sejour = new SejourInfirmerie($hero, $motif, TypeAdmission::MANUELLE, $hero->getPointsDeVieMaximum(), 20, $now);
        match ($statut) {
            StatutSejour::RETOUR_QG => $sejour->commencerRetour(new Coordonnees(45.764, 4.8357), 392.0, 200.0, $now, new \DateTimeImmutable('+2 hours')),
            StatutSejour::RETABLI => $sejour->arriverAuQg($now)->marquerRetabli($now),
            StatutSejour::SORTI => $sejour->arriverAuQg($now)->marquerRetabli($now)->sortir(new \DateTimeImmutable()),
            default => $sejour->arriverAuQg($now),
        };
        self::persister($this->em, $sejour);

        return $sejour;
    }

    private function heroPersiste(array $o = []): Hero
    {
        $hero = Builder::hero($o);
        self::persister($this->em, $hero);

        return $hero;
    }

    public function testBoutonVisibleUniquementPourLesOperateursHeros(): void
    {
        $hero = $this->heroPersiste();
        $this->connecter('ROLE_HERO_OPERATOR');
        $this->client->request('GET', '/heros/'.$hero->getId());
        self::assertSelectorExists('[data-test="envoyer-infirmerie"]:not([disabled])');
        self::assertSelectorExists('dialog form[action$="/envoyer-infirmerie"] input[name="_token"]');
        self::assertSelectorTextContains('dialog', 'Conséquences');

        $this->connecter('ROLE_MISSION_OPERATOR');
        $this->client->request('GET', '/heros/'.$hero->getId());
        self::assertSelectorTextContains('.infirmary-hero', 'Suivi médical');
        self::assertSelectorNotExists('[data-test="envoyer-infirmerie"]');
    }

    public function testBoutonRemplaceParLeStatutSiSejourActif(): void
    {
        $hero = $this->heroPersiste(['statut' => HeroStatut::BLESSE]);
        $this->sejour($hero);
        $this->connecter('ROLE_ADMIN');
        $this->client->request('GET', '/heros/'.$hero->getId());
        self::assertSelectorNotExists('[data-test="envoyer-infirmerie"]');
        self::assertSelectorTextContains('.infirmary-hero', 'En traitement');
    }

    public function testAdmissionManuelleDepuisLaFiche(): void
    {
        $hero = $this->heroPersiste(['pv' => 70]);
        $user = $this->connecter('ROLE_HERO_OPERATOR');
        $crawler = $this->client->request('GET', '/heros/'.$hero->getId());
        $form = $crawler->selectButton('Confirmer l\'admission')->form(['motif' => 'BLESSURE', 'notes' => 'Entorse simulée']);
        $this->client->submit($form);
        self::assertResponseRedirects('/heros/'.$hero->getId());
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash--success', 'admis à l\'infirmerie');

        $sejour = $this->em->getRepository(SejourInfirmerie::class)->findOneBy([]);
        self::assertSame(TypeAdmission::MANUELLE, $sejour->getTypeAdmission());
        self::assertSame(MotifInfirmerie::BLESSURE, $sejour->getMotif());
        self::assertSame($user->getId(), $sejour->getDemandePar()->getId());
        self::assertSame('Entorse simulée', $sejour->getNotes());
        self::assertSame(StatutSejour::EN_TRAITEMENT, $sejour->getStatut(), 'sans position : déjà au QG');
        $this->em->clear();
        self::assertSame(HeroStatut::BLESSE, $this->em->find(Hero::class, $hero->getId())->getStatut());

        // Seconde admission refusée côté serveur.
        $this->client->request('POST', '/heros/'.$hero->getId().'/envoyer-infirmerie', ['_token' => $form['_token']->getValue(), 'motif' => 'BLESSURE']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash--danger', 'déjà pris en charge');
        self::assertSame(1, $this->em->getRepository(SejourInfirmerie::class)->count([]));
    }

    public function testJetonCsrfEtMotifObligatoires(): void
    {
        $hero = $this->heroPersiste();
        $this->connecter('ROLE_HERO_OPERATOR');
        $crawler = $this->client->request('GET', '/heros/'.$hero->getId());
        $jeton = $crawler->filter('dialog input[name="_token"]')->attr('value');

        $this->client->request('POST', '/heros/'.$hero->getId().'/envoyer-infirmerie', ['_token' => 'forge', 'motif' => 'BLESSURE']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash--danger', 'Jeton de sécurité invalide');

        $this->client->request('POST', '/heros/'.$hero->getId().'/envoyer-infirmerie', ['_token' => $jeton, 'motif' => 'RECUPERATION_POST_MISSION']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash--danger', 'motif médical valide');
        self::assertSame(0, $this->em->getRepository(SejourInfirmerie::class)->count([]));

        $this->client->request('GET', '/heros/'.$hero->getId().'/envoyer-infirmerie');
        self::assertResponseStatusCodeSame(405, 'POST uniquement');
    }

    public static function acces(): iterable
    {
        yield ['ROLE_HERO_OPERATOR', 200];
        yield ['ROLE_ADMIN', 200];
        yield ['ROLE_OPERATOR', 200];
        yield ['ROLE_MISSION_OPERATOR', 403];
    }

    #[DataProvider('acces')]
    public function testAccesALaPage(string $role, int $code): void
    {
        $this->connecter($role);
        $this->client->request('GET', '/infirmerie');
        self::assertResponseStatusCodeSame($code);
        if (200 === $code) {
            self::assertSelectorTextContains('.sidebar', 'Infirmerie');
        }
    }

    public function testOperateurMissionsNePeutNiAdmettreNiValiderUneSortie(): void
    {
        $hero = $this->heroPersiste();
        $retabli = $this->sejour($this->heroPersiste(), statut: StatutSejour::RETABLI);
        $this->connecter('ROLE_MISSION_OPERATOR');
        $this->client->request('POST', '/heros/'.$hero->getId().'/envoyer-infirmerie', ['_token' => 'x', 'motif' => 'BLESSURE']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/infirmerie/sejours/'.$retabli->getId().'/sortie', ['_token' => 'x']);
        self::assertResponseStatusCodeSame(403);
        self::assertSame(StatutSejour::RETABLI, $this->em->find(SejourInfirmerie::class, $retabli->getId())->getStatut());
    }

    public function testAffichageFiltresEtHistorique(): void
    {
        $epuise = $this->heroPersiste(['nom' => 'Épuisé', 'fatigue' => 95, 'rang' => 'BETA']);
        $this->sejour($epuise, MotifInfirmerie::EPUISEMENT);
        $enRoute = $this->heroPersiste(['nom' => 'EnRoute', 'pv' => 10, 'rang' => 'ALPHA']);
        $this->sejour($enRoute, MotifInfirmerie::SANTE_CRITIQUE, StatutSejour::RETOUR_QG);
        $ancien = $this->heroPersiste(['nom' => 'Ancien']);
        $this->sejour($ancien, statut: StatutSejour::SORTI);
        $this->connecter('ROLE_HERO_OPERATOR');

        $this->client->request('GET', '/infirmerie');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(2, '.infirmary-card');
        self::assertSelectorTextContains('[data-kpi="retours"]', '1');
        self::assertSelectorTextContains('[data-kpi="traitements"]', '1');
        self::assertSelectorTextContains('[data-kpi="litsDisponibles"]', '4');
        self::assertSelectorTextContains('.table', 'Ancien');
        self::assertSelectorTextContains('[data-sejour] [data-champ="retour"]:not([hidden])', 'km restants');

        foreach (['epuisement' => 'Épuisé', 'retour' => 'EnRoute', 'critique' => 'EnRoute', 'traitement' => 'Épuisé'] as $filtre => $nom) {
            $this->client->request('GET', '/infirmerie?filtre='.$filtre);
            self::assertSelectorCount(1, '.infirmary-card', $filtre);
            self::assertSelectorTextContains('.infirmary-card h3', $nom, $filtre);
        }
        $this->client->request('GET', '/infirmerie?filtre=retablis');
        self::assertSelectorCount(0, '.infirmary-card');
        $this->client->request('GET', '/infirmerie?rang=BETA');
        self::assertSelectorCount(1, '.infirmary-card');
        $this->client->request('GET', '/infirmerie?depuis='.(new \DateTimeImmutable('+1 day'))->format('Y-m-d'));
        self::assertSelectorCount(0, '.infirmary-card');
        $this->client->request('GET', '/infirmerie?filtre=inconnu&depuis=pas-une-date');
        self::assertSelectorCount(2, '.infirmary-card');
    }

    public function testValidationDeSortie(): void
    {
        $hero = $this->heroPersiste(['statut' => HeroStatut::BLESSE]);
        $retabli = $this->sejour($hero, statut: StatutSejour::RETABLI);
        $enCours = $this->sejour($this->heroPersiste(['statut' => HeroStatut::BLESSE]));
        $this->connecter('ROLE_HERO_OPERATOR');

        $crawler = $this->client->request('GET', '/infirmerie');
        self::assertSelectorExists('[data-sejour="'.$enCours->getId().'"] form[data-champ="sortie"][hidden]');
        $jetonEnCours = $crawler->filter('[data-sejour="'.$enCours->getId().'"] form[data-champ="sortie"] input[name="_token"]')->attr('value');
        $this->client->request('POST', '/infirmerie/sejours/'.$enCours->getId().'/sortie', ['_token' => $jetonEnCours]);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash--danger', 'pas encore rétabli');

        $crawler = $this->client->request('GET', '/infirmerie');
        $this->client->submit($crawler->filter('[data-sejour="'.$retabli->getId().'"]')->selectButton('Valider la sortie')->form());
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash--success', 'redevient sélectionnable');
        $this->em->clear();
        $sejour = $this->em->find(SejourInfirmerie::class, $retabli->getId());
        self::assertSame(StatutSejour::SORTI, $sejour->getStatut());
        self::assertNotNull($sejour->getSortiLe());
        self::assertSame(HeroStatut::DISPONIBLE, $this->em->find(Hero::class, $hero->getId())->getStatut());
        self::assertSame(StatutSejour::EN_TRAITEMENT, $this->em->find(SejourInfirmerie::class, $enCours->getId())->getStatut());
    }

    public function testFluxTempsReel(): void
    {
        $hero = $this->heroPersiste(['nom' => 'Rapatrié', 'pv' => 10]);
        $hero->setGeoPosition(GeoPosition::en(45.764, 4.8357));
        self::persister($this->em, $hero);
        $sejour = $this->sejour($hero, MotifInfirmerie::SANTE_CRITIQUE, StatutSejour::RETOUR_QG);
        $this->connecter('ROLE_HERO_OPERATOR');

        $this->client->request('GET', '/api/temps-reel/etat');
        $etat = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame(1, $etat['infirmerie']['indicateurs']['retours']);
        $s = $etat['infirmerie']['sejours'][0];
        self::assertSame($sejour->getId(), $s['id']);
        self::assertSame('RETOUR_QG', $s['statut']);
        self::assertSame(10, $s['sante']);
        self::assertGreaterThan(0, $s['secondesRestantes']);
        self::assertSame('EN_DEPLACEMENT', $s['retour']['statut']);
        $h = array_values(array_filter($etat['heros'], static fn ($x) => $x['id'] === $hero->getId()))[0];
        self::assertSame('RETOUR_QG', $h['infirmerie']['statut']);
        self::assertEqualsWithDelta(48.8566, $h['deplacement']['arrivee']['lat'], 1e-4, 'destination : le QG');
        self::assertNotEquals(45.764, $h['position']['lat'], 'position sur le trajet de retour');

        // Opérateur missions : état général du héros, sans le détail de l'infirmerie.
        $this->connecter('ROLE_MISSION_OPERATOR');
        $this->client->request('GET', '/api/temps-reel/etat');
        $etat = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertArrayNotHasKey('infirmerie', $etat);
        $h = array_values(array_filter($etat['heros'], static fn ($x) => $x['id'] === $hero->getId()))[0];
        self::assertSame('Retour vers le QG', $h['infirmerie']['statutLabel']);
    }

    public function testIndisponibleDansLeSquadBuilderEtDeploiementRefuse(): void
    {
        $mission = Builder::mission();
        // Statut héros remis à « disponible » à la main : seul le séjour actif doit bloquer.
        $soigne = Builder::hero(['nom' => 'Soigné', 'statut' => HeroStatut::DISPONIBLE]);
        $valide = Builder::hero(['nom' => 'Valide']);
        self::persister($this->em, $mission, $soigne, $valide);
        $this->sejour($soigne);
        $this->connecter('ROLE_HERO_OPERATOR');

        $crawler = $this->client->request('GET', '/squad-builder/'.$mission->getId().'?equipe='.$soigne->getId());
        self::assertSelectorTextContains('[data-test="exclus-infirmerie"]', 'Soigné');
        self::assertSelectorTextContains('[data-test="exclus-infirmerie"]', 'Traitement médical');
        self::assertAnySelectorTextContains('.badge', 'Traitement médical (blessure)');

        // Formulaire forgé : le serveur revalide au déploiement.
        $form = $crawler->selectButton('Confirmer l\'équipe et déployer')->form();
        $form['equipe'] = (string) $soigne->getId();
        $this->client->submit($form);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash--danger', 'médicalement indisponible');
        self::assertSame(0, $this->em->getRepository(AffectationMission::class)->count([]));
    }
}
