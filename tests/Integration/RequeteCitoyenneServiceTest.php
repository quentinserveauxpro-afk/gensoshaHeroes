<?php

namespace App\Tests\Integration;

use App\Entity\Alerte;
use App\Entity\Mission;
use App\Entity\RequeteCitoyenne;
use App\Enum\Difficulte;
use App\Enum\MissionStatut;
use App\Enum\NiveauUrgence;
use App\Enum\RequeteStatut;
use App\Service\Requete\RequeteCitoyenneService;
use App\Tests\Support\ResetDatabaseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RequeteCitoyenneServiceTest extends KernelTestCase
{
    use ResetDatabaseTrait;

    private EntityManagerInterface $em;
    private RequeteCitoyenneService $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->service = self::getContainer()->get(RequeteCitoyenneService::class);
        self::viderBase($this->em);
    }

    private function requete(NiveauUrgence $urgence): RequeteCitoyenne
    {
        return (new RequeteCitoyenne())->setTitre('Effondrement du pont')->setDescription('Bus suspendus')
            ->setAdresse('Pylône B-12')->setPositionX(70)->setPositionY(18)
            ->setNiveauUrgence($urgence)->setNomDemandeur('Police Centrale');
    }

    public function testEnregistrementUrgentCreeUneAlerte(): void
    {
        $this->service->enregistrer($this->requete(NiveauUrgence::CRITIQUE));
        $this->service->enregistrer($this->requete(NiveauUrgence::FAIBLE));

        $alertes = $this->em->getRepository(Alerte::class)->findAll();
        self::assertCount(1, $alertes);
        self::assertSame('URGENCE ALPHA', $alertes[0]->getTitre());
        self::assertNotNull($alertes[0]->getRequete());
    }

    public function testConversionEnMissionConserveLeLien(): void
    {
        $requete = $this->requete(NiveauUrgence::CRITIQUE);
        $this->service->enregistrer($requete);

        $mission = $this->service->convertirEnMission($requete);
        $this->em->clear();

        $requete = $this->em->find(RequeteCitoyenne::class, $requete->getId());
        $mission = $this->em->find(Mission::class, $mission->getId());
        self::assertSame($mission->getId(), $requete->getMission()?->getId());
        self::assertSame(RequeteStatut::CONVERTIE, $requete->getStatut());
        self::assertSame('Effondrement du pont', $mission->getNom());
        self::assertSame('Pylône B-12', $mission->getLocalisation());
        self::assertSame(70.0, $mission->getPositionX());
        self::assertSame(NiveauUrgence::CRITIQUE, $mission->getNiveauUrgence());
        self::assertSame(Difficulte::A, $mission->getDifficulte());
        self::assertSame(MissionStatut::EN_ATTENTE, $mission->getStatut());
        self::assertGreaterThan(new \DateTimeImmutable('+40 minutes'), $mission->getDateFinPrevue());
    }

    public function testUneRequeteNePeutEtreConvertieQuUneFois(): void
    {
        $requete = $this->requete(NiveauUrgence::MOYENNE);
        $this->service->enregistrer($requete);
        $this->service->convertirEnMission($requete);

        $this->expectException(\DomainException::class);
        $this->service->convertirEnMission($requete);
    }
}
