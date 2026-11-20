<?php

namespace App\Security\Voter;

use App\Entity\Mission;
use App\Enum\MissionStatut;
use App\Security\RoleCatalog;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Droits dépendant d'une mission.
 *
 * - MISSION_GERER    : opérateur missions, mission non close (informations, objectifs, position) ;
 * - MISSION_DEPLOYER : opérateur héros, mission en brouillon ou en attente (constitution et déploiement).
 * Un opérateur missions ne peut pas déployer ; un opérateur héros ne peut pas modifier la mission.
 *
 * @extends Voter<string, Mission>
 */
final class MissionVoter extends Voter
{
    public const GERER = 'MISSION_GERER';
    public const DEPLOYER = 'MISSION_DEPLOYER';

    public function __construct(private readonly Security $security)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::GERER, self::DEPLOYER], true) && $subject instanceof Mission;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        /** @var Mission $subject */
        return match ($attribute) {
            self::GERER => $this->security->isGranted(RoleCatalog::MISSION_OPERATOR) && !$subject->getStatut()->isClosed(),
            self::DEPLOYER => $this->security->isGranted(RoleCatalog::HERO_OPERATOR)
                && \in_array($subject->getStatut(), [MissionStatut::BROUILLON, MissionStatut::EN_ATTENTE], true),
            default => false,
        };
    }
}
