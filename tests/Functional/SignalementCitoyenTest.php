<?php

namespace App\Tests\Functional;

use App\Entity\RequeteCitoyenne;
use App\Entity\User;
use App\Enum\CategorieIncident;
use App\Enum\StatutTraitement;
use App\Tests\Support\Builder;

final class SignalementCitoyenTest extends AppWebTestCase
{
    private const FORMULAIRE = '/citoyen/signalement';

    /** @param array<string, string|bool> $surcharges (bool = case à cocher) */
    private function soumettre(array $surcharges = []): void
    {
        $crawler = $this->client->request('GET', self::FORMULAIRE);
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Envoyer le signalement')->form();
        $valeurs = array_merge([
            'signalement_citoyen[nom]' => 'Martin',
            'signalement_citoyen[prenom]' => 'Camille',
            'signalement_citoyen[adresse]' => '12 rue de la République',
            'signalement_citoyen[codePostal]' => '69001',
            'signalement_citoyen[ville]' => 'Lyon',
            'signalement_citoyen[categorie]' => CategorieIncident::INCENDIE->value,
            'signalement_citoyen[description]' => 'Fumée noire au deuxième étage.',
        ], $surcharges);
        foreach ($valeurs as $champ => $valeur) {
            if (\is_bool($valeur)) {
                $valeur ? $form[$champ]->tick() : $form[$champ]->untick();
            } else {
                $form[$champ] = $valeur;
            }
        }
        $this->client->submit($form);
    }

    private function seuleRequete(): RequeteCitoyenne
    {
        $this->em->clear();
        $requetes = $this->em->getRepository(RequeteCitoyenne::class)->findAll();
        self::assertCount(1, $requetes);

        return $requetes[0];
    }

    private function nombreRequetes(): int
    {
        return $this->em->getRepository(RequeteCitoyenne::class)->count([]);
    }

    public function testSoumissionAvecNomEtPrenom(): void
    {
        $citoyen = $this->connecter('ROLE_CITOYEN');
        $this->soumettre();

        $requete = $this->seuleRequete();
        self::assertResponseRedirects('/citoyen/signalement/'.$requete->getId().'/confirmation', 303);
        self::assertSame('Martin', $requete->getNomDemandeur());
        self::assertSame('Camille', $requete->getPrenomDemandeur());
        self::assertFalse($requete->isAnonyme());
        self::assertSame('69001', $requete->getCodePostal());
        self::assertSame(CategorieIncident::INCENDIE, $requete->getCategorieIncident());
        self::assertSame(StatutTraitement::NOUVELLE, $requete->getStatutTraitement());
        self::assertSame($citoyen->getId(), $requete->getCitoyen()?->getId());
        self::assertNull($requete->getVillainSignale());
        self::assertNotNull($requete->getDateCreation());
        self::assertNull($requete->getMission(), 'Aucune mission n\'est créée par un citoyen.');

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-test="reference"]', $requete->getReference());
        self::assertSelectorTextContains('main', 'Camille Martin');
    }

    public function testSoumissionAnonymeNEnregistreNiNAfficheLIdentite(): void
    {
        $this->connecter('ROLE_CITOYEN');
        $this->soumettre(['signalement_citoyen[anonyme]' => true, 'signalement_citoyen[nom]' => 'Secret', 'signalement_citoyen[prenom]' => 'Zéphyrine']);

        $requete = $this->seuleRequete();
        self::assertTrue($requete->isAnonyme());
        self::assertSame('Anonyme', $requete->getNomDemandeur());
        self::assertNull($requete->getPrenomDemandeur());

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Signalement anonyme');
        $html = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('Secret', $html);
        self::assertStringNotContainsString('Zéphyrine', $html);
    }

    public function testSoumissionAnonymeSansIdentiteAcceptee(): void
    {
        $this->connecter('ROLE_CITOYEN');
        $this->soumettre(['signalement_citoyen[anonyme]' => true, 'signalement_citoyen[nom]' => '', 'signalement_citoyen[prenom]' => '', 'signalement_citoyen[description]' => '']);

        self::assertResponseStatusCodeSame(303);
        self::assertSame(1, $this->nombreRequetes());
    }

    public function testSoumissionNonAnonymeSansIdentiteRefusee(): void
    {
        $this->connecter('ROLE_CITOYEN');
        $this->soumettre(['signalement_citoyen[nom]' => '', 'signalement_citoyen[prenom]' => ' ']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'Indiquez votre nom, ou cochez « Rester anonyme ».');
        self::assertSelectorTextContains('main', 'Indiquez votre prénom, ou cochez « Rester anonyme ».');
        self::assertSame(0, $this->nombreRequetes());
    }

    /** @return iterable<string, array{string}> */
    public static function codesPostauxInvalides(): iterable
    {
        yield 'quatre chiffres' => ['6900'];
        yield 'six chiffres' => ['690010'];
        yield 'lettres' => ['69A01'];
        yield 'corse (non numérique)' => ['2A004'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('codesPostauxInvalides')]
    public function testCodePostalInvalideRefuse(string $codePostal): void
    {
        $this->connecter('ROLE_CITOYEN');
        $this->soumettre(['signalement_citoyen[codePostal]' => $codePostal]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'Le code postal doit contenir exactement 5 chiffres.');
        self::assertSame(0, $this->nombreRequetes());
    }

    public function testChampsObligatoiresEtDescriptionTropLongue(): void
    {
        $this->connecter('ROLE_CITOYEN');
        $this->soumettre([
            'signalement_citoyen[adresse]' => '',
            'signalement_citoyen[ville]' => '',
            'signalement_citoyen[categorie]' => '',
            'signalement_citoyen[description]' => str_repeat('a', 2001),
        ]);

        self::assertResponseStatusCodeSame(422);
        foreach (['Indiquez l\'adresse de l\'incident.', 'Indiquez la ville.', 'Choisissez la catégorie de l\'incident.', 'La description ne doit pas dépasser 2000 caractères.'] as $message) {
            self::assertSelectorTextContains('main', $message);
        }
        self::assertSame(0, $this->nombreRequetes());
    }

    public function testSoumissionAvecSuperVilain(): void
    {
        $villain = Builder::villain(7);
        self::persister($this->em, $villain);
        $this->connecter('ROLE_CITOYEN');
        $this->soumettre(['signalement_citoyen[presenceVilain]' => true, 'signalement_citoyen[villain]' => (string) $villain->getId()]);

        $requete = $this->seuleRequete();
        self::assertSame($villain->getId(), $requete->getVillainSignale()?->getId());
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', $villain->getNom());
    }

    public function testPresenceVilainSansSelectionRefusee(): void
    {
        $this->connecter('ROLE_CITOYEN');
        $this->soumettre(['signalement_citoyen[presenceVilain]' => true]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'Sélectionnez le super-vilain aperçu');
        self::assertSame(0, $this->nombreRequetes());
    }

    public function testSansPresenceLeVilainEnvoyeEstIgnore(): void
    {
        $villain = Builder::villain(4);
        self::persister($this->em, $villain);
        $this->connecter('ROLE_CITOYEN');
        // Sans JavaScript, la liste reste visible : une sélection sans la case cochée est ignorée.
        $this->soumettre(['signalement_citoyen[villain]' => (string) $villain->getId()]);

        self::assertResponseStatusCodeSame(303);
        self::assertNull($this->seuleRequete()->getVillainSignale());
    }

    public function testVilainInexistantRefuse(): void
    {
        $this->connecter('ROLE_CITOYEN');
        $crawler = $this->client->request('GET', self::FORMULAIRE);
        $form = $crawler->selectButton('Envoyer le signalement')->form();
        $valeurs = $form->getPhpValues();
        $valeurs['signalement_citoyen'] = array_merge($valeurs['signalement_citoyen'], [
            'nom' => 'Martin', 'prenom' => 'Camille', 'adresse' => '1 rue A', 'codePostal' => '75001', 'ville' => 'Paris',
            'categorie' => CategorieIncident::AUTRE->value, 'presenceVilain' => '1', 'villain' => '999999',
        ]);
        $this->client->request('POST', self::FORMULAIRE, $valeurs);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->nombreRequetes());
    }

    public function testJetonCsrfInvalideRefuse(): void
    {
        $this->connecter('ROLE_CITOYEN');
        $this->soumettre(['signalement_citoyen[_token]' => 'jeton-falsifie']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->nombreRequetes());
    }

    public function testFormulaireReserveAuxComptesConnectes(): void
    {
        $this->client->request('GET', self::FORMULAIRE);
        self::assertResponseRedirects('/login');
    }

    /** @return iterable<string, array{string}> */
    public static function rolesPersonnel(): iterable
    {
        yield 'administrateur' => ['ROLE_ADMIN'];
        yield 'opérateur missions' => ['ROLE_MISSION_OPERATOR'];
        yield 'opérateur héros' => ['ROLE_HERO_OPERATOR'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('rolesPersonnel')]
    public function testLePersonnelNAccedePasALEspaceCitoyen(string $role): void
    {
        $this->connecter($role);
        $this->client->request('GET', self::FORMULAIRE);
        self::assertResponseStatusCodeSame(403);
    }

    /** @return iterable<string, array{string, string}> */
    public static function routesInterdites(): iterable
    {
        yield 'centre tactique' => ['GET', '/'];
        yield 'héros' => ['GET', '/heros'];
        yield 'missions' => ['GET', '/missions'];
        yield 'super-vilains' => ['GET', '/admin/vilains'];
        yield 'requêtes' => ['GET', '/requetes'];
        yield 'infirmerie' => ['GET', '/infirmerie'];
        yield 'administration' => ['GET', '/admin/utilisateurs'];
        yield 'flux temps réel' => ['GET', '/api/temps-reel/etat'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('routesInterdites')]
    public function testLeCitoyenNAccedeAAucunEcranInterne(string $methode, string $url): void
    {
        $this->connecter('ROLE_CITOYEN');
        $this->client->request($methode, $url);
        self::assertResponseStatusCodeSame(403);
    }

    public function testLeCitoyenNePeutNiModifierNiSupprimerSaRequete(): void
    {
        $this->connecter('ROLE_CITOYEN');
        $this->soumettre();
        $id = $this->seuleRequete()->getId();

        // Aucune route de modification ou de suppression dans l'espace citoyen.
        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $methode) {
            $this->client->request($methode, '/citoyen/signalement/'.$id.'/confirmation');
            self::assertResponseStatusCodeSame(405, $methode);
        }
        foreach (['/citoyen/signalement/'.$id.'/modifier', '/citoyen/signalement/'.$id.'/supprimer'] as $url) {
            $this->client->request('POST', $url);
            self::assertResponseStatusCodeSame(404, $url);
        }
        // Les routes de traitement opérateur lui sont refusées.
        foreach (['/requetes/'.$id.'/rejeter', '/requetes/'.$id.'/classer', '/requetes/'.$id.'/analyser', '/requetes/'.$id.'/convertir'] as $url) {
            $this->client->request('POST', $url);
            self::assertResponseStatusCodeSame(403, $url);
        }

        $requete = $this->seuleRequete();
        self::assertSame('Martin', $requete->getNomDemandeur());
        self::assertSame(StatutTraitement::NOUVELLE, $requete->getStatutTraitement());
    }

    public function testConfirmationReserveeASonAuteur(): void
    {
        $this->connecter('ROLE_CITOYEN');
        $this->soumettre();
        $id = $this->seuleRequete()->getId();

        $autre = (new User())->setEmail('autre.citoyen@test.local')->setNom('Autre')->setRoles(['ROLE_CITOYEN'])->setPassword('x');
        self::persister($this->em, $autre);
        $this->client->loginUser($autre);
        $this->client->request('GET', '/citoyen/signalement/'.$id.'/confirmation');
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/citoyen/signalement/999999/confirmation');
        self::assertResponseStatusCodeSame(404);
    }

    public function testConnexionCitoyenRedirigeVersSonEspace(): void
    {
        $user = (new User())->setEmail('connexion.citoyen@test.local')->setNom('Citoyen')->setRoles(['ROLE_CITOYEN']);
        $user->setPassword(static::getContainer()->get('security.user_password_hasher')->hashPassword($user, 'mot-de-passe'));
        self::persister($this->em, $user);

        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->filter('form')->form(['_username' => 'connexion.citoyen@test.local', '_password' => 'mot-de-passe']);
        $this->client->submit($form);
        self::assertResponseRedirects('/citoyen/signalement');
    }

    public function testLeCitoyenPeutSeDeconnecter(): void
    {
        $this->connecter('ROLE_CITOYEN');
        $this->client->request('GET', self::FORMULAIRE);
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/logout');
        self::assertResponseRedirects('/login');
        $this->client->request('GET', self::FORMULAIRE);
        self::assertResponseRedirects('/login', null, 'La session doit être fermée.');
    }

    public function testLeSignalementApparaitCoteOperateurSansIdentiteSiAnonyme(): void
    {
        $this->connecter('ROLE_CITOYEN');
        $this->soumettre(['signalement_citoyen[anonyme]' => true, 'signalement_citoyen[nom]' => 'Secret']);
        $id = $this->seuleRequete()->getId();

        $this->connecter('ROLE_MISSION_OPERATOR');
        $this->client->request('GET', '/requetes/'.$id);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Signalement anonyme');
        self::assertStringNotContainsString('Secret', (string) $this->client->getResponse()->getContent());
    }

}
