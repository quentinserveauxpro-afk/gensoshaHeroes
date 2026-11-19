<?php

namespace App\Controller\Admin;

use App\Entity\Villain;
use App\Form\VillainType;
use App\Repository\VillainRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/vilains')]
final class VillainController extends AbstractController
{
    #[Route('', name: 'app_admin_villain_index', methods: ['GET'])]
    public function index(VillainRepository $villains): Response
    {
        return $this->render('admin/villain/index.html.twig', ['villains' => $villains->findBy([], ['niveauMenace' => 'DESC'])]);
    }

    #[Route('/nouveau', name: 'app_admin_villain_new', methods: ['GET', 'POST'])]
    #[Route('/{id}/modifier', name: 'app_admin_villain_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function form(Request $request, EntityManagerInterface $em, ?Villain $villain = null): Response
    {
        $villain ??= new Villain();
        $form = $this->createForm(VillainType::class, $villain);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($villain);
            $em->flush();
            $this->addFlash('success', 'Vilain enregistré.');

            return $this->redirectToRoute('app_admin_villain_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/form.html.twig', [
            'form' => $form,
            'titre' => null === $villain->getId() ? 'Nouveau vilain' : 'Modifier '.$villain->getNom(),
            'retour' => $this->generateUrl('app_admin_villain_index'),
        ]);
    }
}
