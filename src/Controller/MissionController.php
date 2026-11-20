<?php

namespace App\Controller;

use App\Entity\Mission;
use App\Entity\ObjectifMission;
use App\Enum\Difficulte;
use App\Enum\MissionStatut;
use App\Enum\NiveauUrgence;
use App\Form\MissionType;
use App\Form\MissionPositionType;
use App\Form\Model\MissionPosition;
use App\Form\ObjectifMissionType;
use App\Geo\Coordonnees;
use App\Geo\Geocoding\AdressePostale;
use App\Geo\GeolocalisationService;
use App\Realtime\MissionLiveStatusProvider;
use App\Repository\MissionSimulationStateRepository;
use App\Security\RoleCatalog;
use App\Security\Voter\MissionVoter;
use App\Simulation\MissionSimulationService;
use App\Repository\MissionRepository;
use App\Repository\RequeteCitoyenneRepository;
use App\Service\Mission\MissionLifecycleService;
use App\Service\Mission\MissionSuccessCalculator;
use App\Service\Mission\MissionWorkflowException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

// Consultation : tout le personnel ; gestion : opérateurs missions ; lancement : opérateurs héros.
#[IsGranted('ROLE_STAFF')]
#[Route('/missions')]
final class MissionController extends AbstractController
{
    public const PAR_PAGE = 10;

    #[Route('', name: 'app_mission_index', methods: ['GET'])]
    public function index(MissionRepository $missions, MissionLiveStatusProvider $suivi, #[MapQueryParameter] int $page = 1, #[MapQueryParameter] ?MissionStatut $statut = null): Response
    {
        $page = max(1, $page);
        $paginator = $missions->paginer($page, self::PAR_PAGE, $statut);
        $liste = iterator_to_array($paginator);
        // Valeurs initiales identiques au rafraîchissement automatique ; la requête groupée
        // charge aussi objectifs, équipes et héros de la page (pas de N+1 dans le gabarit).
        $etat = $suivi->pourIdentifiants(array_map(static fn ($m) => (int) $m->getId(), $liste));

        return $this->render('mission/index.html.twig', [
            'missions' => $liste,
            'live' => array_column($etat['missions'], null, 'id'),
            'page' => $page,
            'pages' => max(1, (int) ceil(\count($paginator) / self::PAR_PAGE)),
            'statut' => $statut,
            'compteurs' => $missions->compterParStatut(),
        ]);
    }

    #[Route('/{id}', name: 'app_mission_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Mission $mission, MissionSuccessCalculator $calculator, RequeteCitoyenneRepository $requetes, MissionSimulationStateRepository $etats, MissionSimulationService $simulation): Response
    {
        $etat = $etats->findPourMission($mission);

        return $this->render('mission/show.html.twig', [
            'mission' => $mission,
            'pronostic' => $calculator->calculer($mission, $mission->getHerosAffectes()),
            'requete' => $requetes->findOneBy(['mission' => $mission]),
            'simulation' => $etat,
            'phase' => $simulation->phase($mission, $etat),
            'positionForm' => $this->isGranted(MissionVoter::GERER, $mission) ? $this->createForm(MissionPositionType::class, MissionPosition::depuis($mission), [
                'action' => $this->generateUrl('app_mission_position', ['id' => $mission->getId()]),
            ]) : null,
            'objectifForm' => $this->isGranted(RoleCatalog::MISSION_OPERATOR) ? $this->createForm(ObjectifMissionType::class, new ObjectifMission(), [
                'action' => $this->generateUrl('app_mission_objectif_add', ['id' => $mission->getId()]),
            ]) : null,
        ]);
    }

    #[Route('/nouvelle', name: 'app_mission_new', methods: ['GET', 'POST'])]
    #[IsGranted(RoleCatalog::MISSION_OPERATOR)]
    public function new(Request $request, MissionLifecycleService $cycle, GeolocalisationService $geo, EntityManagerInterface $em): Response
    {
        $mission = (new Mission())->setDifficulte(Difficulte::C)->setNiveauUrgence(NiveauUrgence::MOYENNE)
            ->setTauxDeReussite(0)->setDateFinPrevue(new \DateTimeImmutable('+2 hours'));
        $form = $this->createForm(MissionType::class, $mission);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $cycle->creer($mission);
            // Géolocalisation depuis la localisation saisie ; jamais de position inventée.
            if (null !== $mission->getLocalisation() && '' !== trim($mission->getLocalisation())) {
                $position = $geo->depuisAdresse(new AdressePostale($mission->getLocalisation()));
                if (null !== $position) {
                    $mission->setGeoPosition($position);
                    $em->flush();
                }
            }
            $this->addFlash('success', $mission->isGeolocalisee()
                ? 'Mission créée (brouillon) et placée sur la carte.'
                : 'Mission créée (brouillon). Elle n\'est pas encore géolocalisée : précisez sa position.');

            return $this->redirectToRoute('app_mission_show', ['id' => $mission->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('mission/form.html.twig', ['form' => $form, 'mission' => $mission, 'titre' => 'Nouvelle mission']);
    }

    #[Route('/{id}/modifier', name: 'app_mission_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(RoleCatalog::MISSION_OPERATOR)]
    public function edit(Mission $mission, Request $request, EntityManagerInterface $em, MissionSuccessCalculator $calculator): Response
    {
        $form = $this->createForm(MissionType::class, $mission);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ([] !== $mission->getHerosAffectes()) {
                $mission->setTauxDeReussite($calculator->taux($mission, $mission->getHerosAffectes()));
            }
            $em->flush();
            $this->addFlash('success', 'Mission mise à jour.');

            return $this->redirectToRoute('app_mission_show', ['id' => $mission->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('mission/form.html.twig', ['form' => $form, 'mission' => $mission, 'titre' => 'Modifier la mission']);
    }

    #[Route('/{id}/objectifs', name: 'app_mission_objectif_add', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(RoleCatalog::MISSION_OPERATOR)]
    public function ajouterObjectif(Mission $mission, Request $request, EntityManagerInterface $em): Response
    {
        $objectif = (new ObjectifMission())->setOrdreAffichage($mission->getObjectifs()->count() + 1);
        $form = $this->createForm(ObjectifMissionType::class, $objectif);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $mission->addObjectif($objectif);
            $em->persist($objectif);
            $em->flush();
            $this->addFlash('success', 'Objectif ajouté.');
        } else {
            foreach ($form->getErrors(true) as $erreur) {
                $this->addFlash('danger', $erreur->getMessage());
            }
        }

        return $this->redirectToRoute('app_mission_show', ['id' => $mission->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/objectifs/{objectif}/progression', name: 'app_mission_objectif_progress', requirements: ['id' => '\d+', 'objectif' => '\d+'], methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_OPERATOR") or is_granted("ROLE_MISSION_OPERATOR")'))]
    public function progressionObjectif(Mission $mission, ObjectifMission $objectif, Request $request, EntityManagerInterface $em): Response
    {
        $this->verifierObjectif($mission, $objectif, $request, 'objectif'.$objectif->getId());
        $objectif->setProgression($request->getPayload()->getInt('progression'));
        if ($request->getPayload()->getBoolean('termine')) {
            $objectif->setTermine(true);
        }
        $em->flush();

        return $this->redirectToRoute('app_mission_show', ['id' => $mission->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/objectifs/{objectif}/supprimer', name: 'app_mission_objectif_delete', requirements: ['id' => '\d+', 'objectif' => '\d+'], methods: ['POST'])]
    #[IsGranted(RoleCatalog::MISSION_OPERATOR)]
    public function supprimerObjectif(Mission $mission, ObjectifMission $objectif, Request $request, EntityManagerInterface $em): Response
    {
        $this->verifierObjectif($mission, $objectif, $request, 'supprimer-objectif'.$objectif->getId());
        $mission->removeObjectif($objectif);
        $em->remove($objectif);
        $em->flush();
        $this->addFlash('success', 'Objectif supprimé.');

        return $this->redirectToRoute('app_mission_show', ['id' => $mission->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/{action}', name: 'app_mission_transition', requirements: ['id' => '\d+', 'action' => 'publier|lancer|cloturer|annuler'], methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_MISSION_OPERATOR") or is_granted("ROLE_HERO_OPERATOR")'))]
    public function transition(Mission $mission, string $action, Request $request, MissionLifecycleService $cycle): Response
    {
        // Lancer = engager des héros (opérateur héros) ; publier/clôturer/annuler = gestion de mission.
        $this->denyAccessUnlessGranted('lancer' === $action ? RoleCatalog::HERO_OPERATOR : RoleCatalog::MISSION_OPERATOR);
        if (!$this->isCsrfTokenValid($action.$mission->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        try {
            match ($action) {
                'publier' => $cycle->publier($mission),
                'lancer' => $cycle->lancer($mission),
                'cloturer' => $cycle->cloturer($mission, 'succes' === $request->getPayload()->getString('resultat')),
                'annuler' => $cycle->annuler($mission),
            };
            $this->addFlash('success', sprintf('Mission « %s » : %s.', $mission->getNom(), mb_strtolower($mission->getStatut()->label())));
        } catch (MissionWorkflowException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_mission_show', ['id' => $mission->getId()], Response::HTTP_SEE_OTHER);
    }

    /** Position réelle de la mission : géocodage d'une adresse ou saisie manuelle. */
    #[Route('/{id}/position', name: 'app_mission_position', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(MissionVoter::GERER, 'mission')]
    public function position(Mission $mission, Request $request, GeolocalisationService $geo, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(MissionPositionType::class, $donnees = MissionPosition::depuis($mission));
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            foreach ($form->getErrors(true) as $erreur) {
                $this->addFlash('danger', $erreur->getMessage());
            }

            return $this->redirectToRoute('app_mission_show', ['id' => $mission->getId()], Response::HTTP_SEE_OTHER);
        }

        if (null !== $donnees->latitude && null !== $donnees->longitude) {
            $mission->setGeoPosition($geo->manuelle(new Coordonnees($donnees->latitude, $donnees->longitude), $donnees->adresse, $mission->getGeoPosition()));
            $this->addFlash('success', 'Position de la mission enregistrée.');
        } else {
            $position = $geo->depuisAdresse(new AdressePostale((string) $donnees->adresse, $donnees->codePostal, $donnees->ville), $mission->getGeoPosition());
            if (null === $position) {
                $this->addFlash('danger', 'Adresse introuvable : saisissez les coordonnées manuellement.');

                return $this->redirectToRoute('app_mission_show', ['id' => $mission->getId()], Response::HTTP_SEE_OTHER);
            }
            $mission->setGeoPosition($position);
            $this->addFlash('success', sprintf('Mission géolocalisée : %s.', $position->getLibelle() ?? $donnees->adresse));
        }
        $em->flush();

        return $this->redirectToRoute('app_mission_show', ['id' => $mission->getId()], Response::HTTP_SEE_OTHER);
    }

    private function verifierObjectif(Mission $mission, ObjectifMission $objectif, Request $request, string $jeton): void
    {
        if ($objectif->getMission() !== $mission) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid($jeton, $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }
}
