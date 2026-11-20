<?php

namespace App\Security\Voter;

use App\Entity\RequeteCitoyenne;
use App\Entity\User;
use App\Security\RoleCatalog;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Droits d'un citoyen (ROLE_CITOYEN) sur ses signalements.
 *
 * - SIGNALEMENT_CREER        : créer un signalement ;
 * - SIGNALEMENT_CONFIRMATION : consulter la confirmation d'un signalement dont il est l'auteur.
 * Aucun droit de modification ni de suppression n'existe pour un citoyen.
 *
 * @extends Voter<string, RequeteCitoyenne|null>
 */
final class SignalementCitoyenVoter extends Voter
{
    public const CREER = 'SIGNALEMENT_CREER';
    public const CONFIRMATION = 'SIGNALEMENT_CONFIRMATION';

    public function __construct(private readonly Security $security)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return match ($attribute) {
            self::CREER => true,
            self::CONFIRMATION => $subject instanceof RequeteCitoyenne,
            default => false,
        };
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || !$this->security->isGranted(RoleCatalog::CITOYEN)) {
            $vote?->addReason('Réservé aux comptes citoyens.');

            return false;
        }

        return match ($attribute) {
            self::CREER => true,
            self::CONFIRMATION => null !== $subject->getCitoyen() && $subject->getCitoyen()->getId() === $user->getId(),
            default => false,
        };
    }
}
