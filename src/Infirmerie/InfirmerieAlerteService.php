<?php

namespace App\Infirmerie;

use App\Entity\Alerte;
use App\Entity\SejourInfirmerie;
use App\Enum\NiveauAlerte;
use App\Repository\AlerteRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Alertes médicales, sans doublon :
 *  - par séjour, chaque type d'alerte (CRITIQUE, RAPATRIEMENT, ARRIVEE, RETABLI…) n'est émis
 *    qu'une fois : les codes émis sont mémorisés dans SejourInfirmerie::alertesEmises ;
 *  - « infirmerie complète » n'est émise que si aucune alerte du même type n'est en attente
 *    d'acquittement.
 * Les alertes sont visibles des opérateurs (héros et missions) ; l'appelant flush.
 */
final class InfirmerieAlerteService
{
    public const CRITIQUE = 'CRITIQUE';
    public const RAPATRIEMENT = 'RAPATRIEMENT';
    public const ARRIVEE = 'ARRIVEE';
    public const RETABLI = 'RETABLI';
    public const COMPETENCE = 'COMPETENCE';
    public const PREFIXE_PLEIN = 'INF-PLEIN';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AlerteRepository $alertes,
    ) {
    }

    public function pourSejour(SejourInfirmerie $sejour, string $type, string $titre, string $message, NiveauAlerte $niveau, \DateTimeImmutable $t): ?Alerte
    {
        if ($sejour->aEmisAlerte($type)) {
            return null;
        }
        $sejour->marquerAlerte($type);
        $code = substr(sprintf('INF-%s-%d-%s', substr($type, 0, 4), (int) $sejour->getHero()->getId(), $t->format('ymdHis')), 0, 40);
        $alerte = new Alerte($titre, $message, $niveau, $code, $t);
        $alerte->setMission($sejour->getMission());
        $this->em->persist($alerte);

        return $alerte;
    }

    public function infirmerieComplete(int $occupation, int $capacite, \DateTimeImmutable $t): ?Alerte
    {
        if ($occupation < $capacite || $this->alertes->existeNonAcquittee(self::PREFIXE_PLEIN)) {
            return null;
        }
        $alerte = new Alerte(
            'Infirmerie complète',
            sprintf('%d héros pris en charge pour %d lits : la récupération est ralentie au-delà de la capacité.', $occupation, $capacite),
            NiveauAlerte::ALERTE,
            self::PREFIXE_PLEIN.'-'.$t->format('ymdHis'),
            $t,
        );
        $this->em->persist($alerte);

        return $alerte;
    }
}
