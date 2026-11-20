<?php

namespace App\Controller;

use App\Entity\Mission;
use App\Enum\HeroRole;
use App\Enum\MissionStatut;
use App\Movement\HeroMovementService;
use App\Security\Voter\MissionVoter;
use App\Repository\MissionRepository;
use App\Service\Mission\DeploymentException;
use App\Service\Mission\MissionDeploymentService;
use App\Service\Mission\SquadBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

// Constitution et déploiement des équipes : opérateurs héros (et rôles qui en héritent).
#[IsGranted('ROLE_HERO_OPERATOR')]
#[Route('/squad-builder')]
final class SquadBuilderController extends AbstractController
{
    #[Route('', name: 'app_squad_builder', methods: ['GET'])]
    public function choisir(MissionRepository $missions, #[MapQueryParameter] ?int $mission = null): Response
    {
        if (null !== $mission && null !== $missions->find($mission)) {
            return $this->redirectToRoute('app_squad_builder_mission', ['id' => $mission]);
        }
        $candidates = $missions->filtrer([MissionStatut::EN_ATTENTE, MissionStatut::BROUILLON]);
        if ([] !== $candidates) {
            return $this->redirectToRoute('app_squad_builder_mission', ['id' => $candidates[0]->getId()]);
        }

        return $this->render('squad_builder/aucune_mission.html.twig');
    }

    #[Route('/{id}', name: 'app_squad_builder_mission', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function composer(
        Mission $mission,
        SquadBuilder $builder,
        MissionRepository $missions,
        #[MapQueryParameter] ?string $equipe = null,
        #[MapQueryParameter] ?int $taille = null,
        #[MapQueryParameter] string $filtre = 'tous',
        #[MapQueryParameter] ?HeroRole $role = null,
        #[MapQueryParameter] ?int $niveau = null,
        #[MapQueryParameter] ?int $sante = null,
    ): Response {
        $filtre = \in_array($filtre, SquadBuilder::FILTRES, true) ? $filtre : 'tous';
        $etat = $builder->construire($mission, SquadBuilder::parserEquipe($equipe), $taille, [
            'filtre' => $filtre, 'role' => $role, 'niveau' => $niveau, 'sante' => $sante,
        ]);

        return $this->render('squad_builder/index.html.twig', $etat + [
            'mission' => $mission,
            'missionsDisponibles' => $missions->filtrer([MissionStatut::BROUILLON, MissionStatut::EN_ATTENTE]),
            'filtres' => ['filtre' => $filtre, 'role' => $role, 'niveau' => $niveau, 'sante' => $sante],
        ]);
    }

    #[Route('/{id}/deployer', name: 'app_squad_builder_deploy', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(MissionVoter::DEPLOYER, 'mission')]
    public function deployer(Mission $mission, Request $request, MissionDeploymentService $deploiement, HeroMovementService $mouvements, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('deployer'.$mission->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        $ids = SquadBuilder::parserEquipe($request->getPayload()->getString('equipe'));
        $lead = $request->getPayload()->getInt('lead') ?: null;

        try {
            $deploiement->deployer($mission, $ids, $lead);
        } catch (DeploymentException $e) {
            foreach ($e->erreurs as $erreur) {
                $this->addFlash('danger', $erreur);
            }

            return $this->redirectToRoute('app_squad_builder_mission', ['id' => $mission->getId(), 'equipe' => implode(',', $ids)], Response::HTTP_SEE_OTHER);
        }

        // Départ immédiat des héros vers la position réelle de la mission (ligne droite simulée).
        $departs = $mouvements->demarrerPourMission($mission);
        $em->flush();
        $this->addFlash('success', sprintf('Équipe déployée sur « %s » : %d héros en route.', $mission->getNom(), \count($ids)));
        if (!$mission->isGeolocalisee()) {
            $this->addFlash('danger', 'Mission non géolocalisée : les déplacements démarreront dès que sa position sera renseignée.');
        } elseif ([] === $departs) {
            $this->addFlash('success', 'Les héros sont déjà sur place.');
        }

        return $this->redirectToRoute('app_tactical_center', ['mission' => $mission->getId()], Response::HTTP_SEE_OTHER);
    }
}
