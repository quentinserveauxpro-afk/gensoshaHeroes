<?php

namespace App\Tests\Functional;

use App\Entity\GeoPosition;
use App\Entity\HeroMovement;
use App\Entity\User;
use App\Enum\MissionStatut;
use App\Enum\MouvementStatut;
use App\Tests\Support\Builder;
use PHPUnit\Framework\Attributes\DataProvider;

final class RolesEtComptesTest extends AppWebTestCase
{
    public static function pagesParRole(): iterable
    {
        // url, rôle, code attendu
        yield ['/', 'ROLE_MISSION_OPERATOR', 200];
        yield ['/', 'ROLE_HERO_OPERATOR', 200];
        yield ['/missions', 'ROLE_HERO_OPERATOR', 200];
        yield ['/heros', 'ROLE_MISSION_OPERATOR', 200];
        yield ['/missions/nouvelle', 'ROLE_MISSION_OPERATOR', 200];
        yield ['/missions/nouvelle', 'ROLE_HERO_OPERATOR', 403];
        yield ['/requetes', 'ROLE_MISSION_OPERATOR', 200];
        yield ['/requetes', 'ROLE_HERO_OPERATOR', 403];
        yield ['/squad-builder', 'ROLE_HERO_OPERATOR', 200];
        yield ['/squad-builder', 'ROLE_MISSION_OPERATOR', 403];
        yield ['/admin/utilisateurs', 'ROLE_MISSION_OPERATOR', 403];
        yield ['/admin/utilisateurs', 'ROLE_HERO_OPERATOR', 403];
        yield ['/admin/utilisateurs', 'ROLE_ADMIN', 200];
        yield ['/api/temps-reel/etat', 'ROLE_HERO_OPERATOR', 200];
    }

    #[DataProvider('pagesParRole')]
    public function testAccesSelonLeRole(string $url, string $role, int $code): void
    {
        $this->connecter($role);
        $this->client->request('GET', $url);
        self::assertResponseStatusCodeSame($code, $url.' / '.$role);
    }

    public function testAnonymeRedirigeVersLaConnexionSaufPagePublique(): void
    {
        $this->client->request('GET', '/api/temps-reel/etat');
        self::assertResponseRedirects('/login');
        $this->client->request('GET', '/demande-intervention');
        self::assertResponseIsSuccessful();
    }

    public function testOperateurMissionsNePeutPasDeployer(): void
    {
        $mission = Builder::mission(['statut' => MissionStatut::EN_ATTENTE]);
        $hero = Builder::hero();
        self::persister($this->em, $mission, $hero);
        $this->connecter('ROLE_MISSION_OPERATOR');

        $this->client->request('POST', '/squad-builder/'.$mission->getId().'/deployer', ['equipe' => (string) $hero->getId()]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testDeploiementLanceLesDeplacementsVersLaMission(): void
    {
        $mission = Builder::mission(['statut' => MissionStatut::EN_ATTENTE]);
        $mission->setGeoPosition(GeoPosition::en(45.7640, 4.8357));
        $hero = Builder::hero(['nom' => 'Volt']);
        $hero->setGeoPosition(GeoPosition::en(48.8566, 2.3522));
        self::persister($this->em, $mission, $hero);
        $this->connecter('ROLE_HERO_OPERATOR');

        $crawler = $this->client->request('GET', '/squad-builder/'.$mission->getId().'?equipe='.$hero->getId());
        self::assertResponseIsSuccessful();
        $this->client->submit($crawler->filter('form[action$="/deployer"]')->form());
        self::assertResponseRedirects();

        $mouvements = $this->em->getRepository(HeroMovement::class)->findAll();
        self::assertCount(1, $mouvements);
        self::assertSame(MouvementStatut::EN_DEPLACEMENT, $mouvements[0]->getStatut());
        self::assertEqualsWithDelta(391.5, $mouvements[0]->getDistanceKm(), 1.5);
        self::assertGreaterThan($mouvements[0]->getDateDepart(), $mouvements[0]->getDateArriveePrevue());
    }

    public function testAdministrateurCreeUnCompteOperateurMissions(): void
    {
        $this->connecter('ROLE_ADMIN');
        $crawler = $this->client->request('GET', '/admin/utilisateurs/nouveau');
        $this->client->submit($crawler->selectButton('Enregistrer')->form([
            'compte_operateur[email]' => 'nouvel.operateur@genosha.test',
            'compte_operateur[nom]' => 'Nouvel Opérateur',
            'compte_operateur[typeCompte]' => 'ROLE_MISSION_OPERATOR',
            'compte_operateur[actif]' => true,
            'compte_operateur[plainPassword][first]' => 'motdepasse-solide-42',
            'compte_operateur[plainPassword][second]' => 'motdepasse-solide-42',
        ]));
        self::assertResponseRedirects('/admin/utilisateurs');

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'nouvel.operateur@genosha.test']);
        self::assertNotNull($user);
        self::assertSame(['ROLE_MISSION_OPERATOR', 'ROLE_USER'], $user->getRoles());
        self::assertTrue($user->isActif());
        self::assertNotSame('motdepasse-solide-42', $user->getPassword());
    }

    public function testRoleHorsListeControleeRefuse(): void
    {
        $this->connecter('ROLE_ADMIN');
        $crawler = $this->client->request('GET', '/admin/utilisateurs/nouveau');
        $form = $crawler->selectButton('Enregistrer')->form();
        $valeurs = $form->getPhpValues();
        $valeurs['compte_operateur']['email'] = 'pirate@genosha.test';
        $valeurs['compte_operateur']['nom'] = 'Pirate';
        $valeurs['compte_operateur']['typeCompte'] = 'ROLE_SUPER_ADMIN';
        $valeurs['compte_operateur']['plainPassword'] = ['first' => 'motdepasse-solide-42', 'second' => 'motdepasse-solide-42'];
        $this->client->request('POST', '/admin/utilisateurs/nouveau', $valeurs);

        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->em->getRepository(User::class)->findOneBy(['email' => 'pirate@genosha.test']));
    }

    public function testCompteDesactiveEtReinitialisationDuMotDePasse(): void
    {
        $cible = (new User())->setEmail('cible@genosha.test')->setNom('Cible')->setRoles(['ROLE_HERO_OPERATOR'])->setPassword('ancien');
        self::persister($this->em, $cible);
        $admin = $this->connecter('ROLE_ADMIN');

        $crawler = $this->client->request('GET', '/admin/utilisateurs');
        $this->client->submit($crawler->filter('form[action$="/'.$cible->getId().'/activation"]')->form());
        $this->em->clear();
        self::assertFalse($this->em->find(User::class, $cible->getId())->isActif());
        self::assertCount(0, $crawler->filter('form[action$="/'.$admin->getId().'/activation"]'), 'Pas d\'auto-désactivation.');

        $crawler = $this->client->request('GET', '/admin/utilisateurs/'.$cible->getId().'/mot-de-passe');
        $this->client->submit($crawler->selectButton('Réinitialiser')->form([
            'mot_de_passe[plainPassword][first]' => 'nouveau-mot-de-passe-1',
            'mot_de_passe[plainPassword][second]' => 'nouveau-mot-de-passe-1',
        ]));
        $this->em->clear();
        $cible = $this->em->find(User::class, $cible->getId());
        self::assertNotSame('ancien', $cible->getPassword());
        self::assertNotNull($cible->getMotDePasseModifieLe());

        // La session d'un compte désactivé est interrompue.
        $this->client->loginUser($cible);
        $this->client->request('GET', '/missions');
        self::assertResponseRedirects('/login');
    }
}
