<?php

namespace App\Requete;

use App\Entity\Alerte;
use App\Entity\GeoPosition;
use App\Entity\Mission;
use App\Entity\PieceJointe;
use App\Entity\RequeteCitoyenne;
use App\Entity\RequeteHistorique;
use App\Entity\User;
use App\Enum\Difficulte;
use App\Enum\NiveauAlerte;
use App\Enum\NiveauUrgence;
use App\Enum\OrigineAlerte;
use App\Enum\StatutTraitement;
use App\Form\Model\DemandeIntervention;
use App\Form\Model\QualificationRequete;
use App\Form\Model\SignalementCitoyen;
use App\Geo\Coordonnees;
use App\Geo\Geocoding\AdressePostale;
use App\Geo\GeolocalisationService;
use App\Geo\SecteurResolver;
use App\Service\Requete\RequeteCitoyenneService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * Cycle de vie détaillé des requêtes citoyennes :
 *   soumission publique → NOUVELLE → EN_ANALYSE ↔ INFORMATIONS_REQUISES → VALIDEE
 *   → CONVERTIE_EN_MISSION | REJETEE | CLASSEE_SANS_SUITE.
 *
 * Chaque action est historisée (RequeteHistorique : opérateur, date, statuts, commentaire)
 * et le statut historique (RequeteStatut) est maintenu en cohérence.
 * Le formulaire public ne crée jamais de mission : seule convertir() le fait, sur décision
 * d'un opérateur, dans une transaction Doctrine.
 */
final class RequeteTraitementService
{
    public const NOM_ANONYME = 'Anonyme';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RequeteCitoyenneService $requetes,
        private readonly GeolocalisationService $geolocalisation,
        private readonly SecteurResolver $secteurs,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function enregistrerDemandePublique(DemandeIntervention $d, ?PieceJointe $pieceJointe = null): RequeteCitoyenne
    {
        $now = $this->clock->now();
        $requete = (new RequeteCitoyenne())
            ->setTitre(self::nettoyer($d->titre))
            ->setDescription(self::nettoyer($d->description, true))
            ->setAdresse(self::nettoyer($d->adresse))
            ->setComplementAdresse(self::nettoyerOuNull($d->complementAdresse))
            ->setCodePostal(strtoupper(trim((string) $d->codePostal)))
            ->setVille(self::nettoyer($d->ville))
            ->setCategorieIncident($d->categorie)
            ->setUrgenceDeclaree($d->urgence)
            // L'urgence déclarée n'est jamais une décision officielle : l'urgence interne
            // reste au niveau par défaut jusqu'à la qualification par un opérateur.
            ->setNiveauUrgence(NiveauUrgence::MOYENNE)
            ->setUrgenceAQualifier(true)
            ->setOrigine(OrigineAlerte::CIVIL)
            ->setNomDemandeur(self::nettoyerOuNull($d->nom) ?? self::NOM_ANONYME)
            ->setEmailDemandeur(self::nettoyerOuNull($d->email))
            ->setTelephoneDemandeur(self::nettoyerOuNull($d->telephone))
            ->setConsentementLe($now)
            ->setSoumissionPublique(true)
            ->setStatutTraitement(StatutTraitement::NOUVELLE)
            ->setDateCreation($now);
        $requete->setPieceJointe($pieceJointe);

        return $this->enregistrerNouvelle($requete, 'Demande reçue via le formulaire public.', $now);
    }

    /**
     * Signalement d'un citoyen connecté (ROLE_CITOYEN). Anonyme : ni nom ni prénom enregistrés.
     * Le super-vilain n'est conservé que si sa présence est signalée. Aucune mission n'est créée.
     */
    public function enregistrerSignalementCitoyen(SignalementCitoyen $s, User $citoyen): RequeteCitoyenne
    {
        $now = $this->clock->now();
        $villain = $s->presenceVilain ? $s->villain : null;
        $description = self::nettoyerOuNull($s->description, true);
        $requete = (new RequeteCitoyenne())
            ->setTitre(mb_substr(sprintf('%s — %s%s', $s->categorie->label(), self::nettoyer($s->ville), null !== $villain ? ' (super-vilain signalé)' : ''), 0, 255))
            ->setDescription($description ?? 'Aucune description fournie par le citoyen.')
            ->setAdresse(self::nettoyer($s->adresse))
            ->setCodePostal(trim((string) $s->codePostal))
            ->setVille(self::nettoyer($s->ville))
            ->setCategorieIncident($s->categorie)
            ->setNiveauUrgence(NiveauUrgence::MOYENNE)
            ->setUrgenceAQualifier(true)
            ->setOrigine(OrigineAlerte::CIVIL)
            ->setAnonyme($s->anonyme)
            ->setNomDemandeur($s->anonyme ? self::NOM_ANONYME : self::nettoyer($s->nom))
            ->setPrenomDemandeur($s->anonyme ? null : self::nettoyerOuNull($s->prenom))
            ->setVillainSignale($villain)
            ->setCitoyen($citoyen)
            ->setSoumissionPublique(false)
            ->setStatutTraitement(StatutTraitement::NOUVELLE)
            ->setDateCreation($now);

        return $this->enregistrerNouvelle($requete, 'Signalement reçu depuis l\'espace citoyen.', $now);
    }

    /** Secteur, géolocalisation, enregistrement historisé et notification des opérateurs. */
    private function enregistrerNouvelle(RequeteCitoyenne $requete, string $commentaire, \DateTimeImmutable $now): RequeteCitoyenne
    {
        $requete->setSecteur($this->secteurs->secteur($requete->getCodePostal()));
        $requete->setGeoPosition($this->geolocaliser($requete));

        $this->em->wrapInTransaction(function () use ($requete, $commentaire, $now): void {
            $this->em->persist($requete);
            $this->historiser($requete, 'SOUMISSION', null, StatutTraitement::NOUVELLE, null, $commentaire, $now);
            $this->em->flush();
            $this->notifierOperateurs($requete, $now);
            $this->em->flush();
        });

        // Journal applicatif sans aucune donnée personnelle.
        $this->logger->info('Demande citoyenne {reference} enregistrée (catégorie {categorie}, géolocalisée : {geo}).', [
            'reference' => $requete->getReference(),
            'categorie' => $requete->getCategorieIncident()->value,
            'geo' => null !== $requete->getGeoPosition() ? 'oui' : 'non',
        ]);

        return $requete;
    }

    public function analyser(RequeteCitoyenne $requete, User $operateur, ?string $commentaire = null): void
    {
        $this->changerStatut($requete, StatutTraitement::EN_ANALYSE, 'ANALYSE', $operateur, $commentaire);
        $this->em->flush();
    }

    public function qualifier(RequeteCitoyenne $requete, User $operateur, QualificationRequete $q): void
    {
        $this->exigerOuverte($requete);
        $details = [];
        if ($q->urgenceInterne !== $requete->getNiveauUrgence() || $requete->isUrgenceAQualifier()) {
            $details[] = sprintf('urgence interne : %s', $q->urgenceInterne->label());
        }
        $requete->setNiveauUrgence($q->urgenceInterne)->setUrgenceAQualifier(false);
        if ($q->categorie !== $requete->getCategorieIncident()) {
            $details[] = sprintf('catégorie : %s', $q->categorie->label());
        }
        $requete->setCategorieIncident($q->categorie);
        $requete->setSecteur(self::nettoyerOuNull($q->secteur) ?? $this->secteurs->secteur($requete->getCodePostal()));
        $requete->setInformationsComplementaires(self::nettoyerOuNull($q->informations, true));

        if (null !== $q->latitude && null !== $q->longitude) {
            $coordonnees = new Coordonnees($q->latitude, $q->longitude);
            if (null === $requete->getCoordonnees() || !$requete->getCoordonnees()->egale($coordonnees, 1e-6)) {
                $requete->setGeoPosition($this->geolocalisation->manuelle($coordonnees, $requete->getAdresseComplete(), $requete->getGeoPosition()));
                $details[] = 'position corrigée manuellement';
            }
        } elseif ($q->regeocoder) {
            $position = $this->geolocaliser($requete);
            $details[] = null !== $position ? 'adresse géocodée' : 'adresse introuvable au géocodage';
            if (null !== $position) {
                $requete->setGeoPosition($position);
            }
        }

        $commentaire = trim(implode(' ; ', array_filter([implode(', ', $details), self::nettoyerOuNull($q->commentaire, true)])));
        $this->changerStatut($requete, $q->statut, 'QUALIFICATION', $operateur, '' === $commentaire ? null : $commentaire);
        $this->em->flush();
    }

    public function rejeter(RequeteCitoyenne $requete, User $operateur, string $motif): void
    {
        $motif = self::nettoyer($motif, true);
        if (mb_strlen($motif) < 5) {
            throw new \DomainException('Un motif de rejet est obligatoire.');
        }
        $requete->setMotifRejet($motif);
        $this->changerStatut($requete, StatutTraitement::REJETEE, 'REJET', $operateur, $motif);
        $this->em->flush();
    }

    public function classer(RequeteCitoyenne $requete, User $operateur, ?string $commentaire = null): void
    {
        $this->changerStatut($requete, StatutTraitement::CLASSEE_SANS_SUITE, 'CLASSEMENT', $operateur, self::nettoyerOuNull($commentaire, true));
        $this->em->flush();
    }

    /**
     * Crée la mission correspondante dans une transaction : la requête est conservée et liée,
     * seules les données opérationnelles sont recopiées (jamais les coordonnées du demandeur).
     *
     * @throws \DomainException si la requête n'est plus convertible
     */
    public function convertir(RequeteCitoyenne $requete, User $operateur, ?Difficulte $difficulte = null): Mission
    {
        $this->exigerOuverte($requete);
        if (null !== $requete->getMission()) {
            throw new \DomainException('Cette requête est déjà liée à une mission.');
        }

        return $this->em->wrapInTransaction(function () use ($requete, $operateur, $difficulte): Mission {
            $now = $this->clock->now();
            $ancien = $requete->getStatutTraitement();
            $mission = $this->requetes->convertirEnMission($requete, $difficulte);

            $mission->setLocalisation(mb_substr($requete->getAdresseComplete(), 0, 255));
            $source = $requete->getGeoPosition();
            if (null !== $source && null !== ($coordonnees = $source->getCoordonnees())) {
                $position = (new GeoPosition($coordonnees, $source->getSource()))
                    ->setAdresseSaisie($source->getAdresseSaisie())->setLibelle($source->getLibelle())
                    ->setCodePostal($source->getCodePostal())->setVille($source->getVille())->setFournisseur($source->getFournisseur());
                $mission->setGeoPosition($position);
                $this->em->persist($position);
            }

            $requete->setStatutTraitement(StatutTraitement::CONVERTIE_EN_MISSION)
                ->setConvertiePar($operateur)->setDateConversion($now)
                ->setTraiteePar($operateur)->setDateDernierTraitement($now);
            $this->historiser($requete, 'CONVERSION', $ancien, StatutTraitement::CONVERTIE_EN_MISSION, $operateur, sprintf('Mission « %s » créée.', $mission->getNom()), $now);
            $this->em->flush();

            return $mission;
        });
    }

    private function changerStatut(RequeteCitoyenne $requete, StatutTraitement $nouveau, string $action, User $operateur, ?string $commentaire): void
    {
        $this->exigerOuverte($requete);
        $now = $this->clock->now();
        $ancien = $requete->getStatutTraitement();
        $requete->setStatutTraitement($nouveau)->setTraiteePar($operateur)->setDateDernierTraitement($now);
        $this->historiser($requete, $action, $ancien, $nouveau, $operateur, $commentaire, $now);
    }

    private function historiser(RequeteCitoyenne $requete, string $action, ?StatutTraitement $ancien, StatutTraitement $nouveau, ?User $operateur, ?string $commentaire, \DateTimeImmutable $date): void
    {
        $entree = new RequeteHistorique($requete, $action, $ancien, $nouveau, $operateur, $commentaire, $date);
        $requete->ajouterHistorique($entree);
        $this->em->persist($entree);
    }

    private function exigerOuverte(RequeteCitoyenne $requete): void
    {
        if (!$requete->getStatutTraitement()->isOuverte()) {
            throw new \DomainException(sprintf('La requête %s est déjà traitée (%s).', $requete->getReference(), mb_strtolower($requete->getStatutTraitement()->label())));
        }
    }

    private function geolocaliser(RequeteCitoyenne $requete): ?GeoPosition
    {
        return $this->geolocalisation->depuisAdresse(new AdressePostale(
            (string) $requete->getAdresse(),
            $requete->getCodePostal(),
            $requete->getVille(),
            $requete->getComplementAdresse(),
        ), $requete->getGeoPosition());
    }

    /** Alerte persistée visible des opérateurs missions (bandeau, centre tactique). */
    private function notifierOperateurs(RequeteCitoyenne $requete, \DateTimeImmutable $now): void
    {
        $declaree = $requete->getUrgenceDeclaree();
        $alerte = new Alerte(
            'Nouvelle requête citoyenne',
            sprintf('%s • %s • %s%s', $requete->getReference(), $requete->getCategorieIncident()->label(), $requete->getVille() ?? $requete->getSecteur() ?? 'Localisation à préciser',
                null !== $declaree ? ' • urgence déclarée : '.mb_strtolower($declaree->label()) : ''),
            null !== $declaree && $declaree->isUrgent() ? NiveauAlerte::ALERTE : NiveauAlerte::INFO,
            sprintf('CIT-%s-%d', $now->format('His'), $requete->getId()),
            $now,
        );
        $alerte->setRequete($requete);
        $this->em->persist($alerte);
    }

    private static function nettoyer(?string $texte, bool $multiligne = false): string
    {
        $texte = (string) $texte;
        // Suppression des caractères de contrôle (sauf retours à la ligne en multiligne).
        $texte = (string) preg_replace($multiligne ? '/[^\P{C}\n\t]/u' : '/\p{C}/u', '', $texte);

        return trim($multiligne ? $texte : (string) preg_replace('/\s+/u', ' ', $texte));
    }

    private static function nettoyerOuNull(?string $texte, bool $multiligne = false): ?string
    {
        $t = self::nettoyer($texte, $multiligne);

        return '' === $t ? null : $t;
    }
}
