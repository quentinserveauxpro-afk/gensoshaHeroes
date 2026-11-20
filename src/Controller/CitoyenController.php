<?php

namespace App\Controller;

use App\Entity\RequeteCitoyenne;
use App\Entity\User;
use App\Form\Model\SignalementCitoyen;
use App\Form\SignalementCitoyenType;
use App\Requete\RequeteTraitementService;
use App\Security\Voter\SignalementCitoyenVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Espace citoyen (ROLE_CITOYEN) : signaler un incident et consulter la confirmation de son
 * propre signalement. Aucune route de modification ni de suppression n'existe ; les écrans
 * internes (héros, missions, super-vilains, administration) restent réservés au personnel.
 */
#[IsGranted('ROLE_CITOYEN')]
#[Route('/citoyen')]
final class CitoyenController extends AbstractController
{
    #[Route('/signalement', name: 'app_citoyen_signalement', methods: ['GET', 'POST'])]
    #[IsGranted(SignalementCitoyenVoter::CREER)]
    public function signalement(Request $request, RequeteTraitementService $traitement): Response
    {
        $signalement = new SignalementCitoyen();
        $form = $this->createForm(SignalementCitoyenType::class, $signalement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $citoyen */
            $citoyen = $this->getUser();
            $requete = $traitement->enregistrerSignalementCitoyen($signalement, $citoyen);

            return $this->redirectToRoute('app_citoyen_confirmation', ['id' => $requete->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('citoyen/signalement.html.twig', [
            'form' => $form,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /** Confirmation : uniquement pour l'auteur du signalement (404 pour tout autre compte). */
    #[Route('/signalement/{id}/confirmation', name: 'app_citoyen_confirmation', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(SignalementCitoyenVoter::CONFIRMATION, 'requete', message: 'Signalement introuvable.', statusCode: Response::HTTP_NOT_FOUND)]
    public function confirmation(RequeteCitoyenne $requete): Response
    {
        return $this->render('citoyen/confirmation.html.twig', ['requete' => $requete]);
    }
}
