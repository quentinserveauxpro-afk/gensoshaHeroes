<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Tests\Support\ResetDatabaseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class AppWebTestCase extends WebTestCase
{
    use ResetDatabaseTrait;

    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        self::viderBase($this->em);
    }

    protected function connecter(string $role = 'ROLE_ADMIN'): User
    {
        $user = (new User())->setEmail(strtolower($role).'@test.local')->setNom('Testeur')->setRoles([$role])->setPassword('x');
        self::persister($this->em, $user);
        $this->client->loginUser($user);

        return $user;
    }
}
