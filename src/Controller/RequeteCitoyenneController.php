<?php

namespace App\Controller;

use App\Entity\RequeteCitoyenne;
use App\Entity\User;
use App\Enum\CategorieIncident;
use App\Enum\NiveauUrgence;
use App\Enum\RequeteCategorie;
use App\Enum\StatutTraitement;
use App\Requete\RequeteTraitementService;
use App\Security\Voter\RequeteCitoyenneVoter;
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

// Opérateurs missions (et opérateur polyvalent historique) ; jamais les opérateurs héros.
#[IsGranted(RequeteCitoyenneVoter::CONSULTER)]
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
        #[MapQueryParameter] ?NiveauUrgence $urgence = null,
        #[MapQueryParameter] ?CategorieIncident $type = null,
        #[MapQueryParameter] ?string $secteur = null,
        #[MapQueryParameter] ?StatutTraitement $statut = null,
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
            'requetes' => $requetes->rechercher($urgence, $type, $secteur, $statut, $categorie, $urgentes, 'date' === $tri ? 'date' : 'urgence'),
            'filtres' => ['categorie' => $categorie, 'urgentes' => $urgentes, 'tri' => $tri, 'urgence' => $urgence, 'type' => $type, 'secteur' => $secteur, 'statut' => $statut],
            'secteurs' => $requetes->secteursConnus(),
            'parCategorie' => $requetes->compterParCategorie(),
            'total' => $requetes->count([]),
            'urgentesNonTraitees' => $requetes->compterUrgentesNonTraitees(),
            'recues24h' => $requetes->compterDepuis($clock->now()->modify('-24 hours')),
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/{id}/convertir', name: 'app_requete_convert', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(RequeteCitoyenneVoter::CONVERTIR, 'requete')]
    public function convertir(RequeteCitoyenne $requete, Request $request, RequeteTraitementService $service): Response
    {
        if (!$this->isCsrfTokenValid('convertir'.$requete->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        try {
            // Transaction Doctrine : mission + lien + historique, ou rien.
            $mission = $service->convertir($requete, $this->operateur());
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());

            return $this->redirectToRoute('app_requete_index', [], Response::HTTP_SEE_OTHER);
        }
        $this->addFlash('success', sprintf('Requête %s convertie en mission. Constituez maintenant l\'escouade.', $requete->getReference()));

        return $this->redirectToRoute('app_mission_show', ['id' => $mission->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/classer', name: 'app_requete_close', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(RequeteCitoyenneVoter::CLASSER, 'requete')]
    public function classer(RequeteCitoyenne $requete, Request $request, RequeteTraitementService $service): Response
    {
        if (!$this->isCsrfTokenValid('classer'.$requete->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        try {
            $service->classer($requete, $this->operateur(), $request->getPayload()->getString('commentaire') ?: null);
            $this->addFlash('success', sprintf('Requête %s classée sans suite.', $requete->getReference()));
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_requete_index', [], Response::HTTP_SEE_OTHER);
    }

    private function operateur(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
