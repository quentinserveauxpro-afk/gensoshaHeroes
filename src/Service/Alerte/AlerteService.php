<?php

namespace App\Service\Alerte;

use App\Entity\Alerte;
use App\Entity\Mission;
use App\Entity\RequeteCitoyenne;
use App\Enum\NiveauAlerte;
use App\Enum\NiveauUrgence;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Génère des alertes persistées à partir d'événements métier réels.
 * L'appelant est responsable du flush (l'alerte est enregistrée avec l'entité source).
 */
final class AlerteService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
    ) {
    }

    public function pourRequete(RequeteCitoyenne $requete): ?Alerte
    {
        if (!$requete->getNiveauUrgence()->isUrgent()) {
            return null;
        }
        $critique = NiveauUrgence::CRITIQUE === $requete->getNiveauUrgence();
        $alerte = new Alerte(
            $critique ? 'URGENCE ALPHA' : 'Requête urgente',
            sprintf('%s • %s • %s', $requete->getTitre(), $requete->getAdresse(), $requete->getCategorie()->label()),
            $critique ? NiveauAlerte::CRITIQUE : NiveauAlerte::ALERTE,
            $this->code('REQ'),
            $this->clock->now(),
        );
        $alerte->setRequete($requete);
        $this->em->persist($alerte);

        return $alerte;
    }

    public function pourMission(Mission $mission): ?Alerte
    {
        if (!$mission->isCritique()) {
            return null;
        }
        $alerte = new Alerte(
            'Mission critique',
            sprintf('%s • %s • %s', $mission->getNom(), $mission->getLocalisation() ?? 'Localisation inconnue', $mission->getDifficulte()?->label() ?? ''),
            NiveauAlerte::CRITIQUE,
            $this->code('MIS'),
            $this->clock->now(),
        );
        $alerte->setMission($mission);
        $this->em->persist($alerte);

        return $alerte;
    }

    private function code(string $prefixe): string
    {
        return sprintf('%s-%s-%s', $prefixe, $this->clock->now()->format('His'), strtoupper(bin2hex(random_bytes(2))));
    }
}
