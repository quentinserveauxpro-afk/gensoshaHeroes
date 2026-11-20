<?php

namespace App\Security;

/**
 * Liste contrôlée, côté serveur, des types de comptes qu'un administrateur peut attribuer.
 * Aucun autre rôle ne peut être injecté par le formulaire de gestion des comptes.
 */
final class RoleCatalog
{
    public const ADMIN = 'ROLE_ADMIN';
    public const MISSION_OPERATOR = 'ROLE_MISSION_OPERATOR';
    public const HERO_OPERATOR = 'ROLE_HERO_OPERATOR';
    /** Rôle historique, non attribuable aux nouveaux comptes. */
    public const OPERATEUR_HISTORIQUE = 'ROLE_OPERATOR';
    public const STAFF = 'ROLE_STAFF';

    /** @var array<string, string> rôle => libellé */
    public const ATTRIBUABLES = [
        self::MISSION_OPERATOR => 'Opérateur missions',
        self::HERO_OPERATOR => 'Opérateur héros',
        self::ADMIN => 'Administrateur',
    ];

    /** @return array<string, string> libellé => rôle (format ChoiceType) */
    public static function choix(): array
    {
        return array_flip(self::ATTRIBUABLES);
    }

    public static function estAttribuable(string $role): bool
    {
        return isset(self::ATTRIBUABLES[$role]);
    }

    public static function libelle(string $role): string
    {
        return self::ATTRIBUABLES[$role] ?? match ($role) {
            self::OPERATEUR_HISTORIQUE => 'Opérateur polyvalent (historique)',
            default => $role,
        };
    }

    /** Type de compte principal d'une liste de rôles stockés. */
    public static function typeDeCompte(array $roles): ?string
    {
        foreach ([self::ADMIN, self::MISSION_OPERATOR, self::HERO_OPERATOR] as $role) {
            if (\in_array($role, $roles, true)) {
                return $role;
            }
        }

        return null;
    }
}
