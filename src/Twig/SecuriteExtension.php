<?php

namespace App\Twig;

use App\Security\RoleCatalog;
use Twig\Attribute\AsTwigFunction;

final class SecuriteExtension
{
    #[AsTwigFunction('role_libelle')]
    public function libelle(string $role): string
    {
        return RoleCatalog::libelle($role);
    }
}
