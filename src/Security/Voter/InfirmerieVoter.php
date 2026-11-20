<?php

namespace App\Security\Voter;

use App\Entity\Hero;
use App\Entity\SejourInfirmerie;
use App\Enum\StatutSejour;
use App\Infirmerie\HeroMedicalEvaluationService;
use App\Repository\SejourInfirmerieRepository;
use App\Security\RoleCatalog;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Droits de l'infirmerie (vérifiés dans les contrôleurs, pas seulement dans Twig).
 *
 * - INFIRMERIE_CONSULTER : opérateur héros (et administrateur) — page, historique médical ;
 * - INFIRMERIE_ADMETTRE  : opérateur héros, sur un héros sans séjour actif ;
 * - INFIRMERIE_SORTIE    : opérateur héros, sur un séjour au statut « rétabli ».
 * L'opérateur missions consulte l'état général des héros (fiche, mission, alertes) mais
 * ne peut ni admettre ni valider une sortie.
 *
 * @extends Voter<string, Hero|SejourInfirmerie|null>
 */
final class InfirmerieVoter extends Voter
{
    public const CONSULTER = 'INFIRMERIE_CONSULTER';
    public const ADMETTRE = 'INFIRMERIE_ADMETTRE';
    public const SORTIE = 'INFIRMERIE_SORTIE';

    public function __construct(
        private readonly Security $security,
        private readonly SejourInfirmerieRepository $sejours,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return match ($attribute) {
            self::CONSULTER => true,
            self::ADMETTRE => $subject instanceof Hero,
            self::SORTIE => $subject instanceof SejourInfirmerie,
            default => false,
        };
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if (!$this->security->isGranted(RoleCatalog::HERO_OPERATOR)) {
            $vote?->addReason('Réservé aux opérateurs héros et aux administrateurs.');

            return false;
        }

        return match ($attribute) {
            self::CONSULTER => true,
            self::ADMETTRE => null === $this->sejours->findActifPourHero($subject),
            // Sortie : séjour rétabli ET points de vie == maximum ET fatigue == 0.
            self::SORTIE => StatutSejour::RETABLI === $subject->getStatut()
                && HeroMedicalEvaluationService::estRetabli($subject->getHero()->getPointsDeVie(), $subject->getHero()->getPointsDeVieMaximum(), $subject->getHero()->getFatigue()),
            default => false,
        };
    }
}
