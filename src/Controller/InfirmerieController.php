<?php

namespace App\Controller;

use App\Entity\Hero;
use App\Entity\SejourInfirmerie;
use App\Entity\User;
use App\Enum\MotifInfirmerie;
use App\Enum\StatutSejour;
use App\Infirmerie\HeroRecoveryService;
use App\Infirmerie\HeroRepatriationService;
use App\Infirmerie\InfirmerieConfiguration;
use App\Infirmerie\InfirmerieEtatProvider;
use App\Infirmerie\InfirmerieException;
use App\Repository\SejourInfirmerieRepository;
use App\Security\Voter\InfirmerieVoter;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Infirmerie : page de suivi, admission manuelle depuis la fiche héros, validation de sortie.
 * Toutes les actions sont en POST, protégées par jeton CSRF et par InfirmerieVoter.
 */
final class InfirmerieController extends AbstractController
{
    public const FILTRES = ['tous', 'retour', 'traitement', 'retablis', 'critique', 'epuisement'];

    #[Route('/infirmerie', name: 'app_infirmerie', methods: ['GET'])]
    #[IsGranted(InfirmerieVoter::CONSULTER)]
    public function index(
        SejourInfirmerieRepository $sejours,
        InfirmerieEtatProvider $etat,
        InfirmerieConfiguration $config,
        ClockInterface $clock,
        #[MapQueryParameter] string $filtre = 'tous',
        #[MapQueryParameter] ?string $rang = null,
        #[MapQueryParameter] ?string $depuis = null,
    ): Response {
        $filtre = \in_array($filtre, self::FILTRES, true) ? $filtre : 'tous';
        $rang = null !== $rang && '' !== trim($rang) ? mb_substr(trim($rang), 0, 20) : null;
        $dateDepuis = null !== $depuis && 1 === preg_match('/^\d{4}-\d{2}-\d{2}$/', $depuis)
            ? (\DateTimeImmutable::createFromFormat('!Y-m-d', $depuis) ?: null) : null;

        $now = $clock->now();
        $actifs = $sejours->findActifs();
        $donnees = [];
        foreach ($actifs as $sejour) {
            $donnees[$sejour->getId()] = $etat->sejour($sejour, $now);
        }
        $comptes = ['tous' => \count($actifs)];
        foreach (self::FILTRES as $f) {
            $comptes[$f] = \count(array_filter($actifs, fn (SejourInfirmerie $s) => $this->correspond($s, $donnees[$s->getId()], $f)));
        }
        $affiches = array_values(array_filter($actifs, fn (SejourInfirmerie $s) => $this->correspond($s, $donnees[$s->getId()], $filtre)
            && (null === $rang || $s->getHero()->getRang() === $rang)
            && (null === $dateDepuis || $s->getAdmisLe() >= $dateDepuis)));

        return $this->render('infirmerie/index.html.twig', [
            'sejours' => $affiches,
            'donnees' => $donnees,
            'indicateurs' => $etat->indicateurs($actifs),
            'historique' => $sejours->rechercher(false, ['rang' => $rang, 'depuis' => $dateDepuis], 30),
            'comptes' => $comptes,
            'rangs' => $sejours->rangs(),
            'filtres' => ['filtre' => $filtre, 'rang' => $rang, 'depuis' => $dateDepuis?->format('Y-m-d')],
            'config' => $config,
        ]);
    }

    #[Route('/heros/{id}/envoyer-infirmerie', name: 'app_hero_infirmerie', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(InfirmerieVoter::CONSULTER)]
    public function admettre(Hero $hero, Request $request, HeroRepatriationService $rapatriement): RedirectResponse
    {
        $retour = $this->redirectToRoute('app_hero_show', ['id' => $hero->getId()], Response::HTTP_SEE_OTHER);
        if (!$this->isCsrfTokenValid('infirmerie-hero'.$hero->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide : action annulée.');

            return $retour;
        }
        if (!$this->isGranted(InfirmerieVoter::ADMETTRE, $hero)) {
            $this->addFlash('danger', sprintf('%s est déjà pris en charge par l\'infirmerie.', $hero->getNom()));

            return $retour;
        }
        $motif = MotifInfirmerie::tryFrom($request->getPayload()->getString('motif'));
        if (null === $motif || !\in_array($motif, MotifInfirmerie::manuels(), true)) {
            $this->addFlash('danger', 'Indiquez un motif médical valide.');

            return $retour;
        }
        $notes = mb_substr(trim($request->getPayload()->getString('notes')), 0, 2000);

        try {
            $user = $this->getUser();
            $sejour = $rapatriement->admettreManuellement($hero, $motif, $user instanceof User ? $user : null, '' === $notes ? null : $notes);
        } catch (InfirmerieException $e) {
            $this->addFlash('danger', $e->getMessage());

            return $retour;
        }

        $this->addFlash('success', StatutSejour::RETOUR_QG === $sejour->getStatut()
            ? sprintf('%s est rapatrié vers le QG pour traitement (%s).', $hero->getNom(), mb_strtolower($motif->label()))
            : sprintf('%s est admis à l\'infirmerie (%s).', $hero->getNom(), mb_strtolower($motif->label())));

        return $retour;
    }

    #[Route('/infirmerie/sejours/{id}/sortie', name: 'app_infirmerie_sortie', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(InfirmerieVoter::CONSULTER)]
    public function sortie(SejourInfirmerie $sejour, Request $request, HeroRecoveryService $recuperation, EntityManagerInterface $em, ClockInterface $clock): RedirectResponse
    {
        $retour = $this->redirectToRoute('app_infirmerie', [], Response::HTTP_SEE_OTHER);
        if (!$this->isCsrfTokenValid('sortie-infirmerie'.$sejour->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide : action annulée.');

            return $retour;
        }
        if (!$this->isGranted(InfirmerieVoter::SORTIE, $sejour)) {
            $hero = $sejour->getHero();
            $this->addFlash('danger', sprintf('%s n\'est pas encore totalement rétabli (%d / %d PV, fatigue %d) : la sortie exige la santé au maximum et une fatigue à 0.',
                $hero->getNom(), (int) $hero->getPointsDeVie(), $hero->getPointsDeVieMaximum(), $hero->getFatigue()));

            return $retour;
        }
        try {
            $em->wrapInTransaction(function () use ($sejour, $recuperation, $em, $clock): void {
                $em->refresh($sejour);
                $recuperation->sortir($sejour, $clock->now());
            });
        } catch (InfirmerieException $e) {
            $this->addFlash('danger', $e->getMessage());

            return $retour;
        }
        $this->addFlash('success', sprintf('%s quitte l\'infirmerie et redevient sélectionnable dans le Squad Builder.', $sejour->getHero()->getNom()));

        return $retour;
    }

    /** @param array<string, mixed> $d données projetées du séjour */
    private function correspond(SejourInfirmerie $s, array $d, string $filtre): bool
    {
        return match ($filtre) {
            'retour' => $s->getStatut()->isRetour(),
            'traitement' => StatutSejour::EN_TRAITEMENT === $s->getStatut(),
            'retablis' => StatutSejour::RETABLI === $s->getStatut(),
            'critique' => $d['santeCritique'] || MotifInfirmerie::SANTE_CRITIQUE === $s->getMotif(),
            'epuisement' => $d['epuise'] || MotifInfirmerie::EPUISEMENT === $s->getMotif(),
            default => true,
        };
    }
}
