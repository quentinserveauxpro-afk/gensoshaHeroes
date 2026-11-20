<?php

namespace App\Controller;

use App\Entity\RequeteCitoyenne;
use App\Entity\User;
use App\Form\Model\QualificationRequete;
use App\Form\QualificationRequeteType;
use App\Requete\PieceJointeStorage;
use App\Requete\RequeteTraitementService;
use App\Security\Voter\RequeteCitoyenneVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Fiche détaillée d'une requête et actions de traitement des opérateurs missions. */
#[Route('/requetes/{id}', requirements: ['id' => '\d+'])]
final class RequeteTraitementController extends AbstractController
{
    #[Route('', name: 'app_requete_show', methods: ['GET', 'POST'])]
    #[IsGranted(RequeteCitoyenneVoter::CONSULTER, 'requete')]
    public function show(RequeteCitoyenne $requete, Request $request, RequeteTraitementService $service): Response
    {
        $form = null;
        if ($this->isGranted(RequeteCitoyenneVoter::TRAITER, $requete)) {
            $form = $this->createForm(QualificationRequeteType::class, QualificationRequete::depuis($requete), [
                'action' => $this->generateUrl('app_requete_show', ['id' => $requete->getId()]),
            ]);
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                try {
                    $service->qualifier($requete, $this->operateur(), $form->getData());
                    $this->addFlash('success', sprintf('Requête %s qualifiée : %s.', $requete->getReference(), mb_strtolower($requete->getStatutTraitement()->label())));
                } catch (\DomainException $e) {
                    $this->addFlash('danger', $e->getMessage());
                }

                return $this->redirectToRoute('app_requete_show', ['id' => $requete->getId()], Response::HTTP_SEE_OTHER);
            }
        } elseif ($request->isMethod('POST')) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('requete/show.html.twig', [
            'requete' => $requete,
            'form' => $form,
        ], new Response(status: null !== $form && $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/analyser', name: 'app_requete_analyser', methods: ['POST'])]
    #[IsGranted(RequeteCitoyenneVoter::TRAITER, 'requete')]
    public function analyser(RequeteCitoyenne $requete, Request $request, RequeteTraitementService $service): Response
    {
        $this->verifierCsrf('analyser'.$requete->getId(), $request);
        $service->analyser($requete, $this->operateur());
        $this->addFlash('success', sprintf('Requête %s prise en charge.', $requete->getReference()));

        return $this->redirectToRoute('app_requete_show', ['id' => $requete->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/rejeter', name: 'app_requete_rejeter', methods: ['POST'])]
    #[IsGranted(RequeteCitoyenneVoter::TRAITER, 'requete')]
    public function rejeter(RequeteCitoyenne $requete, Request $request, RequeteTraitementService $service): Response
    {
        $this->verifierCsrf('rejeter'.$requete->getId(), $request);
        try {
            $service->rejeter($requete, $this->operateur(), $request->getPayload()->getString('motif'));
            $this->addFlash('success', sprintf('Requête %s rejetée.', $requete->getReference()));
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_requete_show', ['id' => $requete->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/piece-jointe', name: 'app_requete_piece_jointe', methods: ['GET'])]
    #[IsGranted(RequeteCitoyenneVoter::CONSULTER, 'requete')]
    public function pieceJointe(RequeteCitoyenne $requete, PieceJointeStorage $stockage): Response
    {
        $pj = $requete->getPieceJointe() ?? throw $this->createNotFoundException();
        $chemin = $stockage->chemin($pj);
        if (!is_file($chemin)) {
            throw $this->createNotFoundException();
        }
        $response = new BinaryFileResponse($chemin);
        $response->headers->set('Content-Type', $pj->getTypeMime());
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Security-Policy', "default-src 'none'; sandbox");
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $pj->getNomOriginal());
        $response->setPrivate();

        return $response;
    }

    private function verifierCsrf(string $id, Request $request): void
    {
        if (!$this->isCsrfTokenValid($id, $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }

    private function operateur(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
