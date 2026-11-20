<?php

namespace App\Controller;

use App\Realtime\MissionLiveStatusProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Rafraîchissement automatique des fiches de mission (lecture seule, aucune simulation ici).
 *
 * - GET /missions/live-status?ids=12,13,14 : toutes les missions affichées, en une requête ;
 * - GET /missions/{id}/live-status : la mission sélectionnée sur la carte.
 *
 * Mêmes droits que la consultation des missions (personnel). Les identifiants reçus sont
 * filtrés (entiers positifs, 50 au plus) ; une mission inexistante est simplement absente.
 */
#[IsGranted('ROLE_STAFF')]
#[Route('/missions')]
final class MissionLiveStatusController extends AbstractController
{
    #[Route('/live-status', name: 'app_mission_live_status', methods: ['GET'])]
    public function liste(MissionLiveStatusProvider $provider, #[MapQueryParameter] string $ids = ''): JsonResponse
    {
        return $this->reponse($provider->pourIdentifiants(MissionLiveStatusProvider::identifiants($ids)));
    }

    #[Route('/{id}/live-status', name: 'app_mission_live_status_one', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function une(int $id, MissionLiveStatusProvider $provider): JsonResponse
    {
        $etat = $provider->pourIdentifiants([$id]);
        if ([] === $etat['missions']) {
            throw $this->createNotFoundException('Mission introuvable.');
        }

        return $this->reponse($etat);
    }

    /** @param array<string, mixed> $donnees */
    private function reponse(array $donnees): JsonResponse
    {
        $response = $this->json($donnees);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
