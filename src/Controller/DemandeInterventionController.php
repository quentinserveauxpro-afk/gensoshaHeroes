<?php

namespace App\Controller;

use App\Form\DemandeInterventionType;
use App\Form\Model\DemandeIntervention;
use App\Requete\PieceJointeStorage;
use App\Requete\RequeteTraitementService;
use App\Security\AntiBot\AntiBotGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Formulaire public de demande d'intervention (aucun compte requis).
 *
 * Sécurité : CSRF, limitation de débit par adresse IP (hachée), pot de miel + jeton
 * horodaté signé, validation serveur complète, pièce jointe en liste blanche stockée hors
 * du répertoire public. La confirmation est neutre : elle ne révèle que la référence.
 * Aucune mission n'est créée ici : la demande part en file de traitement (statut NOUVELLE).
 */
#[Route('/demande-intervention')]
final class DemandeInterventionController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'limiter.demande_intervention')]
        private readonly RateLimiterFactoryInterface $limiteur,
        private readonly AntiBotGuard $antiBot,
    ) {
    }

    #[Route('', name: 'app_demande_intervention', methods: ['GET', 'POST'])]
    public function nouvelle(Request $request, RequeteTraitementService $traitement, PieceJointeStorage $stockage): Response
    {
        $demande = new DemandeIntervention();
        $form = $this->createForm(DemandeInterventionType::class, $demande, ['jeton_antibot' => $this->antiBot->jeton()]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $limite = $this->limiteur->create(hash('sha256', (string) $request->getClientIp()))->consume();
            if (!$limite->isAccepted()) {
                return $this->render('demande_intervention/limite.html.twig', [
                    'reessayer' => $limite->getRetryAfter(),
                ], new Response(status: Response::HTTP_TOO_MANY_REQUESTS, headers: ['Retry-After' => (string) max(1, $limite->getRetryAfter()->getTimestamp() - time())]));
            }

            if (!$this->antiBot->estHumain($form->get('horodatage')->getData(), $form->get('site_web')->getData())) {
                // Réponse identique à un succès pour ne pas renseigner un robot.
                return $this->redirectToRoute('app_demande_intervention_confirmation', [], Response::HTTP_SEE_OTHER);
            }

            if ($form->isValid()) {
                $pieceJointe = null;
                if (null !== $demande->pieceJointe) {
                    try {
                        $pieceJointe = $stockage->stocker($demande->pieceJointe);
                    } catch (\Throwable) {
                        $this->addFlash('danger', 'La pièce jointe n\'a pas pu être enregistrée. Votre demande a été transmise sans elle.');
                    }
                }
                $requete = $traitement->enregistrerDemandePublique($demande, $pieceJointe);
                $request->getSession()->set('demande_intervention_reference', $requete->getReference());

                return $this->redirectToRoute('app_demande_intervention_confirmation', [], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('demande_intervention/nouvelle.html.twig', [
            'form' => $form,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/confirmation', name: 'app_demande_intervention_confirmation', methods: ['GET'])]
    public function confirmation(Request $request): Response
    {
        $reference = $request->hasSession() ? $request->getSession()->remove('demande_intervention_reference') : null;

        return $this->render('demande_intervention/confirmation.html.twig', ['reference' => $reference]);
    }
}
