<?php

namespace App\Tests\Functional;

use App\Entity\RequeteCitoyenne;
use App\Entity\User;
use App\Tests\Support\Builder;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SecurityTest extends AppWebTestCase
{
    public function testAnonymeRedirigeVersLaConnexion(): void
    {
        $this->client->request('GET', '/');
        self::assertResponseRedirects('/login');
    }

    public function testConnexionParFormulaire(): void
    {
        $user = (new User())->setEmail('vance@genosha.test')->setNom('Vance')->setRoles(['ROLE_OPERATOR']);
        $user->setPassword(static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, 'secret123'));
        self::persister($this->em, $user);

        $this->client->request('GET', '/login');
        $this->client->submitForm('Se connecter', ['_username' => 'vance@genosha.test', '_password' => 'secret123']);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Centre Tactique');
    }

    /** @return iterable<array{string}> */
    public static function pagesAdmin(): iterable
    {
        yield ['/heros/nouveau'];
        yield ['/missions/nouvelle'];
        yield ['/admin/vilains'];
        yield ['/admin/utilisateurs'];
    }

    #[DataProvider('pagesAdmin')]
    public function testOperateurNAPasAccesALaGestion(string $url): void
    {
        $this->connecter('ROLE_OPERATOR');
        $this->client->request('GET', $url);
        self::assertResponseStatusCodeSame(403);
    }

    #[DataProvider('pagesAdmin')]
    public function testAdminAAccesALaGestion(string $url): void
    {
        $this->connecter('ROLE_ADMIN');
        $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
    }

    public function testOperateurNePeutPasConvertirNiCloturer(): void
    {
        $requete = (new RequeteCitoyenne())->setTitre('T')->setDescription('D')->setAdresse('A')->setNomDemandeur('N');
        $mission = Builder::mission();
        self::persister($this->em, $requete, $mission);
        $this->connecter('ROLE_OPERATOR');

        $this->client->request('POST', '/requetes/'.$requete->getId().'/convertir');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/missions/'.$mission->getId().'/annuler');
        self::assertResponseStatusCodeSame(403);
    }

    public function testActionSensibleSansJetonCsrfRefusee(): void
    {
        $mission = Builder::mission();
        self::persister($this->em, $mission);
        $this->connecter('ROLE_ADMIN');

        $this->client->request('POST', '/missions/'.$mission->getId().'/annuler', ['_token' => 'faux']);
        self::assertResponseStatusCodeSame(403);
    }
}
