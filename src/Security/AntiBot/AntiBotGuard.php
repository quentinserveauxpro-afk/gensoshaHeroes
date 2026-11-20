<?php

namespace App\Security\AntiBot;

use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Protection anti-robot sans service tiers ni JavaScript obligatoire :
 *  - champ « pot de miel » invisible que seuls les robots remplissent ;
 *  - jeton horodaté signé (HMAC sur le secret de l'application) : un envoi plus rapide
 *    que DELAI_MIN ou plus tardif que DELAI_MAX est refusé.
 */
final class AntiBotGuard
{
    public const DELAI_MIN = 3;
    public const DELAI_MAX = 7200;

    public function __construct(
        #[Autowire('%kernel.secret%')] private readonly string $secret,
        private readonly ClockInterface $clock,
        #[Autowire('%env(int:ANTIBOT_DELAI_MIN)%')] private readonly int $delaiMin = self::DELAI_MIN,
    ) {
    }

    public function jeton(): string
    {
        $t = (string) $this->clock->now()->getTimestamp();

        return $t.'.'.hash_hmac('sha256', $t, $this->secret);
    }

    public function estHumain(?string $jeton, ?string $potDeMiel): bool
    {
        if (null !== $potDeMiel && '' !== trim($potDeMiel)) {
            return false;
        }
        if (null === $jeton || !preg_match('/^(\d{1,12})\.([a-f0-9]{64})$/', $jeton, $m)) {
            return false;
        }
        if (!hash_equals(hash_hmac('sha256', $m[1], $this->secret), $m[2])) {
            return false;
        }
        $age = $this->clock->now()->getTimestamp() - (int) $m[1];

        return $age >= $this->delaiMin && $age <= self::DELAI_MAX;
    }
}
