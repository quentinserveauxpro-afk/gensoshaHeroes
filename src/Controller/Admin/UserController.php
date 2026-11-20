<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\CompteOperateurType;
use App\Form\MotDePasseType;
use App\Repository\UserRepository;
use App\Security\RoleCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gestion des comptes internes : réservée aux administrateurs (access_control ^/admin + attribut).
 * Un administrateur ne peut ni se désactiver ni se retirer lui-même le rôle administrateur
 * (évite de perdre le dernier accès d'administration).
 */
#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/utilisateurs')]
final class UserController extends AbstractController
{
    #[Route('', name: 'app_admin_user_index', methods: ['GET'])]
    public function index(UserRepository $users): Response
    {
        return $this->render('admin/user/index.html.twig', ['users' => $users->findBy([], ['email' => 'ASC'])]);
    }

    #[Route('/nouveau', name: 'app_admin_user_new', methods: ['GET', 'POST'])]
    #[Route('/{id}/modifier', name: 'app_admin_user_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function form(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher, ClockInterface $clock, ?User $user = null): Response
    {
        $creation = null === $user;
        $user ??= new User();
        $form = $this->createForm(CompteOperateurType::class, $user, [
            'creation' => $creation,
            'type_actuel' => $creation ? null : RoleCatalog::typeDeCompte($user->getRoles()),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $type = $form->get('typeCompte')->getData();
            if (null !== $type && !RoleCatalog::estAttribuable($type)) {
                throw $this->createAccessDeniedException('Rôle non attribuable.');
            }
            if ($this->estSoiMeme($user) && ((null !== $type && RoleCatalog::ADMIN !== $type) || !$user->isActif())) {
                $em->refresh($user);
                $this->addFlash('danger', 'Vous ne pouvez ni désactiver votre propre compte ni retirer votre rôle administrateur.');

                return $this->redirectToRoute('app_admin_user_edit', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
            }
            if (null !== $type) {
                $user->setRoles([$type]);
            }
            if ($creation) {
                $user->setPassword($hasher->hashPassword($user, (string) $form->get('plainPassword')->getData()));
                $user->setMotDePasseModifieLe($clock->now());
            }
            $em->persist($user);
            $em->flush();
            $this->addFlash('success', sprintf('Compte %s enregistré (%s).', $user->getEmail(), RoleCatalog::libelle(RoleCatalog::typeDeCompte($user->getRoles()) ?? RoleCatalog::OPERATEUR_HISTORIQUE)));

            return $this->redirectToRoute('app_admin_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/user/form.html.twig', [
            'form' => $form,
            'compte' => $user,
            'creation' => $creation,
            'titre' => $creation ? 'Nouveau compte opérateur' : 'Modifier '.$user->getEmail(),
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/{id}/activation', name: 'app_admin_user_toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function basculer(User $user, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('activation'.$user->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        if ($this->estSoiMeme($user)) {
            $this->addFlash('danger', 'Vous ne pouvez pas désactiver votre propre compte.');
        } else {
            $user->setActif(!$user->isActif());
            $em->flush();
            $this->addFlash('success', sprintf('Compte %s %s.', $user->getEmail(), $user->isActif() ? 'activé' : 'désactivé'));
        }

        return $this->redirectToRoute('app_admin_user_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/mot-de-passe', name: 'app_admin_user_password', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function motDePasse(User $user, Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher, ClockInterface $clock): Response
    {
        $form = $this->createForm(MotDePasseType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword($hasher->hashPassword($user, (string) $form->get('plainPassword')->getData()));
            $user->setMotDePasseModifieLe($clock->now());
            $em->flush();
            $this->addFlash('success', sprintf('Mot de passe de %s réinitialisé. Communiquez-le par un canal sûr.', $user->getEmail()));

            return $this->redirectToRoute('app_admin_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/user/password.html.twig', ['form' => $form, 'compte' => $user],
            new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    private function estSoiMeme(User $user): bool
    {
        $moi = $this->getUser();

        return $moi instanceof User && null !== $user->getId() && $moi->getId() === $user->getId();
    }
}
