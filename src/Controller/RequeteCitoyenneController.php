<?php

namespace App\Controller;

use App\Entity\RequeteCitoyenne;
use App\Enum\RequeteCategorie;
use App\Form\RequeteCitoyenneType;
use App\Repository\RequeteCitoyenneRepository;
use App\Service\Requete\RequeteCitoyenneService;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_OPERATOR')]
#[Route('/requetes')]
final class RequeteCitoyenneController extends AbstractController
{
    #[Route('', name: 'app_requete_index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        RequeteCitoyenneRepository $requetes,
        RequeteCitoyenneService $service,
        ClockInterface $clock,
        #[MapQueryParameter] ?RequeteCategorie $categorie = null,
        #[MapQueryParameter] bool $urgentes = false,
        #[MapQueryParameter] string $tri = 'urgence',
    ): Response {
        $requete = new RequeteCitoyenne();
        $form = $this->createForm(RequeteCitoyenneType::class, $requete);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $service->enregistrer($requete);
            $this->addFlash('success', sprintf('Requête %s enregistrée.', $requete->getReference()));

            return $this->redirectToRoute('app_requete_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('requete/index.html.twig', [
            'form' => $form,
            'requetes' => $requetes->filtrer($categorie, $urgentes, 'date' === $tri ? 'date' : 'urgence'),
            'filtres' => ['categorie' => $categorie, 'urgentes' => $urgentes, 'tri' => $tri],
            'parCategorie' => $requetes->compterParCategorie(),
            'total' => $requetes->count([]),
            'urgentesNonTraitees' => $requetes->compterUrgentesNonTraitees(),
            'recues24h' => $requetes->compterDepuis($clock->now()->modify('-24 hours')),
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/{id}/convertir', name: 'app_requete_convert', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function convertir(RequeteCitoyenne $requete, Request $request, RequeteCitoyenneService $service): Response
    {
        if (!$this->isCsrfTokenValid('convertir'.$requete->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        try {
            $mission = $service->convertirEnMission($requete);
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());

            return $this->redirectToRoute('app_requete_index', [], Response::HTTP_SEE_OTHER);
        }
        $this->addFlash('success', sprintf('Requête %s convertie en mission. Constituez maintenant l\'escouade.', $requete->getReference()));

        return $this->redirectToRoute('app_mission_show', ['id' => $mission->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/classer', name: 'app_requete_close', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function classer(RequeteCitoyenne $requete, Request $request, RequeteCitoyenneService $service): Response
    {
        if (!$this->isCsrfTokenValid('classer'.$requete->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        try {
            $service->classer($requete);
            $this->addFlash('success', sprintf('Requête %s classée sans suite.', $requete->getReference()));
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_requete_index', [], Response::HTTP_SEE_OTHER);
    }
}
