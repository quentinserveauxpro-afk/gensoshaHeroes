<?php

namespace App\Security\Voter;

use App\Entity\RequeteCitoyenne;
use App\Security\RoleCatalog;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Droits sur les requêtes citoyennes.
 *
 * - REQUETE_CONSULTER : opérateur missions (ou opérateur polyvalent historique) ;
 * - REQUETE_TRAITER   : opérateur missions ET requête encore ouverte (analyse, qualification, rejet) ;
 * - REQUETE_CLASSER   : idem, ouvert aussi à l'opérateur polyvalent historique (droit antérieur conservé) ;
 * - REQUETE_CONVERTIR : opérateur missions ET requête ouverte non encore liée à une mission.
 * Un opérateur héros n'a aucun de ces droits.
 *
 * @extends Voter<string, RequeteCitoyenne|null>
 */
final class RequeteCitoyenneVoter extends Voter
{
    public const CONSULTER = 'REQUETE_CONSULTER';
    public const TRAITER = 'REQUETE_TRAITER';
    public const CLASSER = 'REQUETE_CLASSER';
    public const CONVERTIR = 'REQUETE_CONVERTIR';

    public function __construct(private readonly Security $security)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return match ($attribute) {
            self::CONSULTER => null === $subject || $subject instanceof RequeteCitoyenne,
            self::TRAITER, self::CLASSER, self::CONVERTIR => $subject instanceof RequeteCitoyenne,
            default => false,
        };
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $operateurMissions = $this->security->isGranted(RoleCatalog::MISSION_OPERATOR);
        $historique = $this->security->isGranted(RoleCatalog::OPERATEUR_HISTORIQUE);

        if (self::CONSULTER === $attribute) {
            return $operateurMissions || $historique;
        }

        /** @var RequeteCitoyenne $subject */
        $ouverte = $subject->getStatutTraitement()->isOuverte();

        return match ($attribute) {
            self::TRAITER => $operateurMissions && $ouverte,
            self::CLASSER => ($operateurMissions || $historique) && $ouverte,
            self::CONVERTIR => $operateurMissions && $ouverte && null === $subject->getMission(),
            default => false,
        };
    }
}
