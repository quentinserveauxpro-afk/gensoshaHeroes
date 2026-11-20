<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Termine immédiatement la session d'un utilisateur dont le compte vient d'être désactivé
 * (le UserChecker ne s'applique qu'à la connexion). Le firewall recharge l'utilisateur depuis
 * la base à chaque requête : ce contrôle voit donc l'état à jour.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 7)]
final class CompteDesactiveListener
{
    public function __construct(
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $user = $this->security->getUser();
        if (!$user instanceof User || $user->isActif()) {
            return;
        }
        $this->security->logout(false);
        $event->setResponse(new RedirectResponse($this->urls->generate('app_login')));
    }
}
