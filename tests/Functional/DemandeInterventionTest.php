<?php

namespace App\Tests\Functional;

use App\Entity\Alerte;
use App\Entity\Mission;
use App\Entity\RequeteCitoyenne;
use App\Enum\CategorieIncident;
use App\Enum\NiveauUrgence;
use App\Enum\StatutTraitement;

final class DemandeInterventionTest extends AppWebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        static::getContainer()->get('cache.rate_limiter')->clear();
    }

    /** @param array<string, mixed> $surcharges */
    private function soumettre(array $surcharges = []): void
    {
        $crawler = $this->client->request('GET', '/demande-intervention');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Envoyer la demande')->form();
        $valeurs = array_merge([
            'demande_intervention[titre]' => 'Incendie dans un entrepôt',
            'demande_intervention[description]' => 'Fumée noire épaisse visible depuis la rue, des personnes seraient encore à l\'intérieur.',
            'demande_intervention[categorie]' => CategorieIncident::INCENDIE->value,
            'demande_intervention[urgence]' => NiveauUrgence::HAUTE->value,
            'demande_intervention[adresse]' => '12 rue de la République',
            'demande_intervention[codePostal]' => '69001',
            'demande_intervention[ville]' => 'Lyon',
            'demande_intervention[email]' => 'temoin@example.org',
            'demande_intervention[consentement]' => '1',
        ], $surcharges);
        foreach ($valeurs as $champ => $valeur) {
            if ('demande_intervention[consentement]' === $champ) {
                '1' === $valeur ? $form[$champ]->tick() : $form[$champ]->untick();
            } else {
                $form[$champ] = $valeur;
            }
        }
        $this->client->submit($form);
    }

    public function testFormulaireAccessibleSansCompte(): void
    {
        $this->client->request('GET', '/demande-intervention');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Demander l\'intervention');
        self::assertSelectorExists('input[name="demande_intervention[_token]"]');
        self::assertSelectorNotExists('.sidebar');
    }

    public function testSoumissionCreeUneRequeteNouvelleSansMission(): void
    {
        $this->soumettre();
        self::assertResponseRedirects('/demande-intervention/confirmation');
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'Référence de votre demande');

        $requetes = $this->em->getRepository(RequeteCitoyenne::class)->findAll();
        self::assertCount(1, $requetes);
        $r = $requetes[0];
        self::assertSame(StatutTraitement::NOUVELLE, $r->getStatutTraitement());
        self::assertSame(CategorieIncident::INCENDIE, $r->getCategorieIncident());
        self::assertSame(NiveauUrgence::HAUTE, $r->getUrgenceDeclaree());
        self::assertSame(NiveauUrgence::MOYENNE, $r->getNiveauUrgence(), 'L\'urgence officielle n\'est pas décidée par le citoyen.');
        self::assertTrue($r->isUrgenceAQualifier());
        self::assertSame('Anonyme', $r->getNomDemandeur());
        self::assertSame('69 · Rhône', $r->getSecteur());
        self::assertNotNull($r->getConsentementLe());
        self::assertNotNull($r->getCoordonnees(), 'Adresse géocodée (référentiel hors ligne en test).');
        self::assertNull($r->getMission());
        self::assertSame(0, $this->em->getRepository(Mission::class)->count([]));
        self::assertCount(1, $r->getHistorique());

        $alertes = $this->em->getRepository(Alerte::class)->findAll();
        self::assertCount(1, $alertes, 'Les opérateurs missions sont notifiés.');
        self::assertStringNotContainsString('temoin@example.org', $alertes[0]->getMessage());
    }

    public function testValidationServeur(): void
    {
        $this->soumettre(['demande_intervention[titre]' => '', 'demande_intervention[codePostal]' => '1234', 'demande_intervention[email]' => '', 'demande_intervention[consentement]' => '0']);
        self::assertResponseStatusCodeSame(422);
        $texte = $this->client->getCrawler()->filter('form')->text();
        self::assertStringContainsString('Code postal français invalide', $texte);
        self::assertStringContainsString('Indiquez un e-mail ou un téléphone', $texte);
        self::assertStringContainsString('consentement', $texte);
        self::assertSame(0, $this->em->getRepository(RequeteCitoyenne::class)->count([]));
    }

    public function testPotDeMielIgnoreSilencieusementLesRobots(): void
    {
        $this->soumettre(['demande_intervention[site_web]' => 'http://spam.example']);
        self::assertResponseRedirects('/demande-intervention/confirmation');
        self::assertSame(0, $this->em->getRepository(RequeteCitoyenne::class)->count([]));
    }

    public function testJetonCsrfObligatoire(): void
    {
        $this->soumettre(['demande_intervention[_token]' => 'jeton-invalide']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em->getRepository(RequeteCitoyenne::class)->count([]));
    }

    public function testLimitationDeDebit(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->soumettre();
        }
        $this->soumettre();
        self::assertResponseStatusCodeSame(429);
        self::assertSelectorTextContains('h1', 'Trop de demandes');
        self::assertSame(5, $this->em->getRepository(RequeteCitoyenne::class)->count([]));
    }
}
