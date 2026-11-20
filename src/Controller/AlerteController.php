<?php

namespace App\Controller;

use App\Entity\Alerte;
use App\Entity\Mission;
use App\Service\Mission\MissionTimer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_STAFF')]
final class AlerteController extends AbstractController
{
    #[Route('/alertes/{id}/acquitter', name: 'app_alerte_ack', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function acquitter(Alerte $alerte, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('acquitter'.$alerte->getId(), $request->getPayload()->getString('_token'))) {
            $alerte->acquitter();
            $em->flush();
        }

        return $this->redirect($request->headers->get('referer') ?: $this->generateUrl('app_tactical_center'), Response::HTTP_SEE_OTHER);
    }

    /** Valeur de référence du timer, utilisée par le navigateur pour se resynchroniser. */
    #[Route('/api/missions/{id}/timer', name: 'app_api_mission_timer', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function timer(Mission $mission, MissionTimer $timer): JsonResponse
    {
        $snapshot = $timer->snapshot($mission);

        return $this->json([
            'mission' => $mission->getId(),
            'secondesRestantes' => $snapshot->secondesRestantes,
            'termine' => $snapshot->termine,
            'libelle' => $snapshot->libelle,
            'echeance' => $snapshot->echeance?->format(\DATE_ATOM),
        ]);
    }
}
