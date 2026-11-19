<?php

namespace App\Tests\Support;

use App\Entity\Aptitude;
use App\Entity\Bonus;
use App\Entity\Hero;
use App\Entity\HeroBonus;
use App\Entity\Mission;
use App\Entity\Villain;
use App\Enum\Difficulte;
use App\Enum\HeroRole;
use App\Enum\MissionStatut;
use App\Enum\NiveauUrgence;
use App\Enum\Statistique;

/** Fabrique d'entités en mémoire pour les tests. */
final class Builder
{
    private static int $sequence = 1000;

    /** @param array<string, mixed> $o */
    public static function hero(array $o = []): Hero
    {
        $hero = (new Hero())
            ->setNom($o['nom'] ?? 'Hero'.++self::$sequence)
            ->setRang($o['rang'] ?? 'ALPHA')
            ->setRole($o['role'] ?? HeroRole::ASSAUT)
            ->setNiveau($o['niveau'] ?? 40)
            ->setXp($o['xp'] ?? 0)
            ->setPointsDeVieMaximum($o['pvMax'] ?? 100)
            ->setPointsDeVie($o['pv'] ?? 100)
            ->setCombat($o['combat'] ?? 80)
            ->setIntelligence($o['intelligence'] ?? 80)
            ->setCharisme($o['charisme'] ?? 80)
            ->setMobilite($o['mobilite'] ?? 80)
            ->setVigueur($o['vigueur'] ?? 80)
            ->setPositionX($o['x'] ?? 50.0)
            ->setPositionY($o['y'] ?? 50.0)
            ->setFatigue($o['fatigue'] ?? 0);
        if (isset($o['statut'])) {
            $hero->setStatut($o['statut']);
        }
        foreach ($o['aptitudes'] ?? [] as $aptitude) {
            $hero->addAptitude($aptitude);
        }

        return $hero;
    }

    /** @param array<string, mixed> $o */
    public static function mission(array $o = []): Mission
    {
        return (new Mission())
            ->setNom($o['nom'] ?? 'Mission '.++self::$sequence)
            ->setDescription($o['description'] ?? 'Description')
            ->setDifficulte($o['difficulte'] ?? Difficulte::C)
            ->setTauxDeReussite(0)
            ->setCharismeRequis($o['charismeRequis'] ?? 0)
            ->setCombatRequis($o['combatRequis'] ?? null)
            ->setMobiliteRequis($o['mobiliteRequis'] ?? null)
            ->setDateFinPrevue($o['fin'] ?? new \DateTimeImmutable('+1 hour'))
            ->setPositionX($o['x'] ?? 50.0)
            ->setPositionY($o['y'] ?? 50.0)
            ->setStatut($o['statut'] ?? MissionStatut::EN_ATTENTE)
            ->setNiveauUrgence($o['urgence'] ?? NiveauUrgence::MOYENNE)
            ->setTailleEquipeMinimale($o['min'] ?? 1)
            ->setTailleEquipeMaximale($o['max'] ?? 4)
            ->setSeuilSanteMinimal($o['seuil'] ?? 30);
    }

    public static function villain(int $menace, int $combat = 50, int $intelligence = 50): Villain
    {
        return (new Villain())->setNom('Vilain '.++self::$sequence)->setNiveauMenace($menace)->setCombat($combat)->setIntelligence($intelligence);
    }

    public static function aptitude(string $code): Aptitude
    {
        return new Aptitude($code, ucfirst(strtolower($code)));
    }

    public static function bonus(Hero $hero, Statistique $stat, int $valeur, bool $cumulable = true, ?string $expiration = null, ?string $debut = '-1 day'): HeroBonus
    {
        $bonus = (new Bonus())->setCode('B'.++self::$sequence)->setNom('Bonus')->setStatistique($stat)->setValeurParDefaut($valeur);
        $heroBonus = (new HeroBonus($bonus, $valeur))->setCumulable($cumulable)
            ->setDateDebut(new \DateTimeImmutable($debut))
            ->setDateExpiration(null !== $expiration ? new \DateTimeImmutable($expiration) : null);
        $hero->addHeroBonus($heroBonus);

        return $heroBonus;
    }
}
