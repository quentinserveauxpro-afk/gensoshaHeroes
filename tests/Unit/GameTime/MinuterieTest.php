<?php

namespace App\Tests\Unit\GameTime;

use App\Twig\InfirmerieExtension;
use PHPUnit\Framework\TestCase;

/** Format d'affichage des comptes à rebours : jamais négatif, NaN ni infini. */
final class MinuterieTest extends TestCase
{
    public function testFormats(): void
    {
        self::assertSame('09:42', InfirmerieExtension::minuterie(582));
        self::assertSame('04:15', InfirmerieExtension::minuterie(255));
        self::assertSame('00:08', InfirmerieExtension::minuterie(8));
        self::assertSame('10:00', InfirmerieExtension::minuterie(600));
        self::assertSame('15:00', InfirmerieExtension::minuterie(900));
        self::assertSame('00:00', InfirmerieExtension::minuterie(-30));
        self::assertSame('—', InfirmerieExtension::minuterie(null));
        self::assertSame('—', InfirmerieExtension::minuterie(NAN));
        self::assertSame('—', InfirmerieExtension::minuterie(INF));
    }
}
