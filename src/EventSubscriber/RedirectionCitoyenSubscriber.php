<?php

namespace App\EventSubscriber;

use App\Security\RoleCatalog;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Après connexion, un citoyen est dirigé vers son espace (/citoyen) : la page par défaut
 * (centre tactique) est réservée au personnel et lui serait refusée.
 */
final readonly class RedirectionCitoyenSubscriber implements EventSubscriberInterface
{
    public function __construct(private UrlGeneratorInterface $urls)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [LoginSuccessEvent::class => 'surConnexion'];
    }

    public function surConnexion(LoginSuccessEvent $event): void
    {
        $roles = $event->getAuthenticatedToken()->getRoleNames();
        if (!\in_array(RoleCatalog::CITOYEN, $roles, true) || \in_array(RoleCatalog::STAFF, $roles, true)) {
            return;
        }
        $reponse = $event->getResponse();
        $cible = $reponse instanceof RedirectResponse ? (string) parse_url($reponse->getTargetUrl(), \PHP_URL_PATH) : '';
        if (!str_starts_with($cible, '/citoyen')) {
            $event->setResponse(new RedirectResponse($this->urls->generate('app_citoyen_signalement')));
        }
    }
}
