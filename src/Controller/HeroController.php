<?php

namespace App\Controller;

use App\Entity\Hero;
use App\Enum\HeroRole;
use App\Form\HeroType;
use App\Repository\HeroRepository;
use App\Service\Mission\EffectiveStatsResolver;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_STAFF')]
#[Route('/heros')]
final class HeroController extends AbstractController
{
    public const PAR_PAGE = 5;

    #[Route('', name: 'app_hero_index', methods: ['GET'])]
    #[Route('/{id}', name: 'app_hero_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function index(
        HeroRepository $heroes,
        EffectiveStatsResolver $stats,
        ClockInterface $clock,
        ?Hero $hero = null,
        #[MapQueryParameter] int $page = 1,
        #[MapQueryParameter] ?string $q = null,
        #[MapQueryParameter] ?HeroRole $role = null,
        #[MapQueryParameter] string $tri = 'niveau',
    ): Response {
        $page = max(1, $page);
        $paginator = $heroes->paginer($page, self::PAR_PAGE, $q, $role, $tri);
        $total = \count($paginator);
        $liste = iterator_to_array($paginator);
        $hero ??= $liste[0] ?? null;

        return $this->render('hero/index.html.twig', [
            'heros' => $liste,
            'hero' => $hero,
            'statsEffectives' => null !== $hero ? $stats->statsEffectives($hero, $clock->now()) : [],
            'bonusActifs' => null !== $hero ? $hero->getBonusEnVigueur($clock->now()) : [],
            'page' => $page,
            'pages' => max(1, (int) ceil($total / self::PAR_PAGE)),
            'total' => $total,
            'filtres' => ['q' => $q, 'role' => $role, 'tri' => $tri],
            'parRole' => $heroes->compterParRole(),
            'parStatut' => $heroes->compterParStatut(),
            'effectif' => $heroes->count([]),
        ]);
    }

    #[Route('/nouveau', name: 'app_hero_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $hero = (new Hero())->setNiveau(1)->setXp(0)->setPointsDeVie(100)->setRang('BETA')
            ->setCombat(50)->setIntelligence(50)->setCharisme(50)->setMobilite(50)->setVigueur(50)
            ->setPositionX(50)->setPositionY(50);

        return $this->formulaire($request, $em, $hero, 'Enrôler un nouveau métahumain', 'Héros enrôlé.');
    }

    #[Route('/{id}/modifier', name: 'app_hero_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Hero $hero, Request $request, EntityManagerInterface $em): Response
    {
        return $this->formulaire($request, $em, $hero, 'Modifier '.$hero->getNom(), 'Fiche mise à jour.');
    }

    #[Route('/{id}/supprimer', name: 'app_hero_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Hero $hero, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('supprimer-hero'.$hero->getId(), $request->getPayload()->getString('_token'))) {
            if (null !== $hero->getAffectationActive()) {
                $this->addFlash('danger', 'Impossible de supprimer un héros engagé en mission.');

                return $this->redirectToRoute('app_hero_show', ['id' => $hero->getId()], Response::HTTP_SEE_OTHER);
            }
            $em->remove($hero);
            $em->flush();
            $this->addFlash('success', 'Héros retiré du registre.');
        }

        return $this->redirectToRoute('app_hero_index', [], Response::HTTP_SEE_OTHER);
    }

    private function formulaire(Request $request, EntityManagerInterface $em, Hero $hero, string $titre, string $message): Response
    {
        $form = $this->createForm(HeroType::class, $hero);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($hero);
            $em->flush();
            $this->addFlash('success', $message);

            return $this->redirectToRoute('app_hero_show', ['id' => $hero->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('hero/form.html.twig', ['form' => $form, 'hero' => $hero, 'titre' => $titre]);
    }
}
