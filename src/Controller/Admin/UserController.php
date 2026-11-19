<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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
    public function form(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher, ?User $user = null): Response
    {
        $creation = null === $user;
        $user ??= new User();
        $form = $this->createForm(UserType::class, $user, ['mot_de_passe_requis' => $creation]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $plain = (string) $form->get('plainPassword')->getData();
            if ('' !== $plain) {
                $user->setPassword($hasher->hashPassword($user, $plain));
            }
            $em->persist($user);
            $em->flush();
            $this->addFlash('success', 'Utilisateur enregistré.');

            return $this->redirectToRoute('app_admin_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/form.html.twig', [
            'form' => $form,
            'titre' => $creation ? 'Nouvel utilisateur' : 'Modifier '.$user->getEmail(),
            'retour' => $this->generateUrl('app_admin_user_index'),
        ]);
    }
}
