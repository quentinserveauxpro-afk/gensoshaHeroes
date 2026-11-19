<?php

namespace App\Tests\Support;

use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Vide la base de test avant chaque test (base « kentybdd_test » créée par
 * doctrine:database:create --env=test puis doctrine:migrations:migrate --env=test).
 */
trait ResetDatabaseTrait
{
    protected static function viderBase(EntityManagerInterface $em): void
    {
        $em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        (new ORMPurger($em))->purge();
        $em->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        $em->clear();
    }

    protected static function persister(EntityManagerInterface $em, object ...$entites): void
    {
        foreach ($entites as $entite) {
            $em->persist($entite);
        }
        $em->flush();
    }
}
