<?php

namespace App\Service\Mission;

use App\Entity\AffectationMission;
use App\Entity\Mission;
use App\Enum\AffectationStatut;
use App\Enum\HeroStatut;
use App\Enum\MissionStatut;
use App\Enum\RoleEquipe;
use App\Repository\HeroRepository;
use App\Repository\MissionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Déploie une équipe sur une mission dans une transaction Doctrine.
 *
 * La mission et les héros sont relus avec un verrou d'écriture (SELECT … FOR UPDATE) :
 * si deux opérateurs déploient le même héros simultanément, le second attend la fin de la
 * transaction du premier, puis voit l'affectation active et est refusé par le validateur.
 */
final class MissionDeploymentService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MissionRepository $missions,
        private readonly HeroRepository $heroes,
        private readonly DeploymentValidator $validator,
        private readonly MissionSuccessCalculator $calculator,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @param list<int> $heroIds identifiants fournis par le client (non fiables)
     *
     * @return list<AffectationMission>
     *
     * @throws DeploymentException
     */
    public function deployer(Mission $mission, array $heroIds, ?int $leadId = null): array
    {
        $heroIds = array_values(array_map('intval', $heroIds));

        $connexion = $this->em->getConnection();
        $connexion->beginTransaction();
        try {
            $affectations = $this->deployerDansTransaction($mission, $heroIds, $leadId);
            $connexion->commit();

            return $affectations;
        } catch (\Throwable $e) {
            $connexion->rollBack();
            throw $e;
        }
    }

    /**
     * @param list<int> $heroIds
     *
     * @return list<AffectationMission>
     */
    private function deployerDansTransaction(Mission $mission, array $heroIds, ?int $leadId): array
    {
        $mission = $this->missions->findPourMiseAJour((int) $mission->getId()) ?? throw new DeploymentException(['Mission introuvable.']);
        $this->em->refresh($mission);

        $verrouilles = $this->heroes->findPourMiseAJour(array_values(array_unique($heroIds)));
        if (\count($verrouilles) !== \count(array_unique($heroIds))) {
            throw new DeploymentException(['Un ou plusieurs héros sont introuvables.']);
        }
        foreach ($verrouilles as $hero) {
            $this->em->refresh($hero);
        }
        $parId = [];
        foreach ($verrouilles as $hero) {
            $parId[$hero->getId()] = $hero;
        }
        $equipe = array_map(static fn (int $id) => $parId[$id], $heroIds);

        $rapport = $this->validator->valider($mission, $equipe);
        if (!$rapport->estValide()) {
            throw new DeploymentException($rapport->erreurs);
        }

        $now = $this->clock->now();
        $leadId ??= $heroIds[0] ?? null;
        $affectations = [];
        foreach ($equipe as $hero) {
            $affectation = (new AffectationMission($hero, $mission, $now))
                ->setRole($hero->getId() === $leadId ? RoleEquipe::LEAD : RoleEquipe::MEMBRE)
                ->setStatut(AffectationStatut::AFFECTE);
            $mission->addAffectation($affectation);
            $hero->addAffectation($affectation);
            $hero->setStatut(HeroStatut::EN_MISSION);
            $this->em->persist($affectation);
            $affectations[] = $affectation;
        }

        $mission->setTauxDeReussite($this->calculator->taux($mission, $equipe));
        $mission->setStatut(MissionStatut::EN_COURS);
        $mission->setDateDebut($now);

        $this->em->flush();

        return $affectations;
    }
}
