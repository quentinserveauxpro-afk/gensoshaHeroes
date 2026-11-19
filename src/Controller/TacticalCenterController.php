<?php

namespace App\Controller;

use App\Entity\Mission;
use App\Enum\Difficulte;
use App\Enum\MissionStatut;
use App\Enum\NiveauUrgence;
use App\Repository\AlerteRepository;
use App\Repository\HeroRepository;
use App\Repository\MissionRepository;
use App\Service\Map\TacticalMapProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_OPERATOR')]
final class TacticalCenterController extends AbstractController
{
    #[Route('/', name: 'app_tactical_center', methods: ['GET'])]
    public function index(
        MissionRepository $missions,
        HeroRepository $heroes,
        TacticalMapProvider $carte,
        AlerteRepository $alertes,
        #[MapQueryParameter] ?MissionStatut $statut = null,
        #[MapQueryParameter] ?Difficulte $difficulte = null,
        #[MapQueryParameter] ?NiveauUrgence $urgence = null,
        #[MapQueryParameter] ?int $mission = null,
    ): Response {
        $statuts = null !== $statut ? [$statut] : MissionStatut::actifs();
        $liste = $missions->filtrer($statuts, $difficulte, $urgence);
        $actives = $missions->filtrer(MissionStatut::actifs());

        $selection = null;
        foreach ($liste as $m) {
            if ($m->getId() === $mission) {
                $selection = $m;
            }
        }
        $selection ??= null !== $mission ? $missions->find($mission) : null;
        $selection ??= $this->premiereEnCours($liste) ?? ($liste[0] ?? null);

        $heros = $heroes->findAll();
        $parStatut = $heroes->compterParStatut();

        $engagees = array_filter($actives, static fn (Mission $m) => [] !== $m->getAffectationsActives());
        $protection = [] === $engagees ? 0.0 : round(array_sum(array_map(static fn (Mission $m) => (int) $m->getTauxDeReussite(), $engagees)) / \count($engagees), 1);

        return $this->render('tactical_center/index.html.twig', [
            'protection' => $protection,
            'alerte' => $alertes->findActives(1)[0] ?? null,
            'missions' => $liste,
            'selection' => $selection,
            'carte' => $carte->construire($actives, $heros, $selection),
            'filtres' => ['statut' => $statut, 'difficulte' => $difficulte, 'urgence' => $urgence],
            'compteurs' => $missions->compterParStatut(),
            'nbActives' => \count($actives),
            'herosDeployes' => $parStatut['EN_MISSION'] ?? 0,
            'herosReserve' => $parStatut['DISPONIBLE'] ?? 0,
            'herosTotal' => \count($heros),
        ]);
    }

    /** @param list<Mission> $missions */
    private function premiereEnCours(array $missions): ?Mission
    {
        foreach ($missions as $mission) {
            if (MissionStatut::EN_COURS === $mission->getStatut()) {
                return $mission;
            }
        }

        return null;
    }
}
