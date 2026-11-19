<?php

namespace App\Service\Requete;

use App\Entity\Mission;
use App\Entity\RequeteCitoyenne;
use App\Enum\Difficulte;
use App\Enum\MissionStatut;
use App\Enum\NiveauUrgence;
use App\Enum\RequeteStatut;
use App\Service\Alerte\AlerteService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final class RequeteCitoyenneService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AlerteService $alertes,
        private readonly ClockInterface $clock,
    ) {
    }

    public function enregistrer(RequeteCitoyenne $requete): void
    {
        $requete->setDateCreation($this->clock->now());
        $this->em->persist($requete);
        $this->alertes->pourRequete($requete);
        $this->em->flush();
    }

    /**
     * Crée une mission (brouillon) à partir de la requête et conserve le lien.
     *
     * @throws \DomainException si la requête n'est plus ouverte
     */
    public function convertirEnMission(RequeteCitoyenne $requete, ?Difficulte $difficulte = null): Mission
    {
        if (null !== $requete->getMission() || !$requete->getStatut()->isOuverte()) {
            throw new \DomainException('Cette requête a déjà été traitée.');
        }
        $urgence = $requete->getNiveauUrgence();
        $now = $this->clock->now();

        $mission = new Mission();
        $mission->setNom($requete->getTitre());
        $mission->setDescription($requete->getDescription());
        $mission->setDifficulte($difficulte ?? self::difficultePourUrgence($urgence));
        $mission->setTauxDeReussite(0);
        $mission->setCharismeRequis(0);
        $mission->setDateFinPrevue($now->modify(sprintf('+%d minutes', self::delaiMinutes($urgence))));
        $mission->setLocalisation($requete->getAdresse());
        $mission->setPositionX($requete->getPositionX());
        $mission->setPositionY($requete->getPositionY());
        $mission->setNiveauUrgence($urgence);
        $mission->setStatut(MissionStatut::EN_ATTENTE);
        $mission->setTailleEquipeMinimale(NiveauUrgence::CRITIQUE === $urgence ? 2 : 1);
        $mission->setTailleEquipeMaximale(NiveauUrgence::CRITIQUE === $urgence ? 5 : 4);

        $requete->setMission($mission);
        $requete->setStatut(RequeteStatut::CONVERTIE);

        $this->em->persist($mission);
        $this->alertes->pourMission($mission);
        $this->em->flush();

        return $mission;
    }

    public function classer(RequeteCitoyenne $requete): void
    {
        if (!$requete->getStatut()->isOuverte()) {
            throw new \DomainException('Cette requête a déjà été traitée.');
        }
        $requete->setStatut(RequeteStatut::CLASSEE);
        $this->em->flush();
    }

    public static function difficultePourUrgence(NiveauUrgence $urgence): Difficulte
    {
        return match ($urgence) {
            NiveauUrgence::FAIBLE => Difficulte::D,
            NiveauUrgence::MOYENNE => Difficulte::C,
            NiveauUrgence::HAUTE => Difficulte::B,
            NiveauUrgence::CRITIQUE => Difficulte::A,
        };
    }

    public static function delaiMinutes(NiveauUrgence $urgence): int
    {
        return match ($urgence) {
            NiveauUrgence::FAIBLE => 24 * 60,
            NiveauUrgence::MOYENNE => 6 * 60,
            NiveauUrgence::HAUTE => 120,
            NiveauUrgence::CRITIQUE => 45,
        };
    }
}
