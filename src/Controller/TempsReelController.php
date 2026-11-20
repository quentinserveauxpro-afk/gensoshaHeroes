<?php

namespace App\Controller;

use App\Realtime\EtatTactiqueProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Diffusion de la situation tactique aux navigateurs connectés.
 *
 * - /api/temps-reel/flux : Server-Sent Events. Un événement « etat » toutes les INTERVALLE
 *   secondes pendant au plus REALTIME_SSE_DUREE secondes, puis fermeture : EventSource se
 *   reconnecte seul (retry 3 s). La session est libérée avant le flux pour ne pas bloquer
 *   les autres requêtes de l'utilisateur.
 * - /api/temps-reel/etat : même instantané en JSON, utilisé en repli (polling) et au
 *   rechargement de la page.
 *
 * Aucun calcul métier ici : le moteur (app:missions:update) fait évoluer l'état, ces
 * endpoints ne font que le lire.
 */
#[IsGranted('ROLE_STAFF')]
#[Route('/api/temps-reel')]
final class TempsReelController extends AbstractController
{
    public const INTERVALLE = 3;

    #[Route('/etat', name: 'app_api_temps_reel_etat', methods: ['GET'])]
    public function etat(EtatTactiqueProvider $provider): JsonResponse
    {
        $response = $this->json($provider->instantane() + ['intervalle' => self::INTERVALLE]);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    #[Route('/flux', name: 'app_api_temps_reel_flux', methods: ['GET'])]
    public function flux(
        Request $request,
        EtatTactiqueProvider $provider,
        EntityManagerInterface $em,
        #[Autowire('%env(int:REALTIME_SSE_DUREE)%')] int $duree = 30,
    ): StreamedResponse {
        if ($request->hasSession()) {
            $request->getSession()->save();
        }

        $response = new StreamedResponse(static function () use ($provider, $em, $duree): void {
            $fin = microtime(true) + max(0, $duree);
            echo "retry: 3000\n\n";
            do {
                $em->clear();
                $donnees = $provider->instantane() + ['intervalle' => self::INTERVALLE];
                echo 'id: '.time()."\n";
                echo "event: etat\n";
                echo 'data: '.json_encode($donnees, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE)."\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
                if (connection_aborted() || microtime(true) + self::INTERVALLE > $fin) {
                    break;
                }
                sleep(self::INTERVALLE);
            } while (true);
        });
        $response->headers->set('Content-Type', 'text/event-stream; charset=utf-8');
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }
}
