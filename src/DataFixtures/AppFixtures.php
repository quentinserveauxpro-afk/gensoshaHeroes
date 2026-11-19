<?php

namespace App\DataFixtures;

use App\Entity\AffectationMission;
use App\Entity\Alerte;
use App\Entity\Aptitude;
use App\Entity\Bonus;
use App\Entity\Hero;
use App\Entity\HeroBonus;
use App\Entity\Mission;
use App\Entity\ObjectifMission;
use App\Entity\RequeteCitoyenne;
use App\Entity\Synergie;
use App\Entity\User;
use App\Entity\Villain;
use App\Enum\AffectationStatut;
use App\Enum\BonusOrigine;
use App\Enum\Difficulte;
use App\Enum\HeroRole;
use App\Enum\HeroStatut;
use App\Enum\MissionStatut;
use App\Enum\NiveauAlerte;
use App\Enum\NiveauUrgence;
use App\Enum\OrigineAlerte;
use App\Enum\RequeteCategorie;
use App\Enum\RequeteStatut;
use App\Enum\RoleEquipe;
use App\Enum\Statistique;
use App\Enum\VillainStatut;
use App\Service\Mission\MissionSuccessCalculator;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Jeu de données réaliste reprenant les exemples des maquettes.
 * Comptes : admin@genosha.test / admin  —  operateur@genosha.test / operateur
 */
class AppFixtures extends Fixture
{
    /** @var array<string, Hero> */
    private array $heros = [];

    /** @var array<string, Aptitude> */
    private array $aptitudes = [];

    /** @var array<string, Villain> */
    private array $villains = [];

    /** @var array<string, Bonus> */
    private array $bonus = [];

    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
        private readonly MissionSuccessCalculator $calculator,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $now = new \DateTimeImmutable();

        $this->chargerUtilisateurs($manager);
        $this->chargerAptitudes($manager);
        $this->chargerBonus($manager);
        $this->chargerHeros($manager, $now);
        $this->chargerSynergies($manager);
        $this->chargerVillains($manager);
        $missions = $this->chargerMissions($manager, $now);
        $this->chargerRequetes($manager, $now, $missions);

        $manager->flush();
    }

    private function chargerUtilisateurs(ObjectManager $manager): void
    {
        foreach ([
            ['admin@genosha.test', 'admin', 'Cmdt. V. Vance', 'Dispatch Principal', ['ROLE_ADMIN']],
            ['operateur@genosha.test', 'operateur', 'Lt. R. Okafor', 'Opérateur Dispatch', ['ROLE_OPERATOR']],
        ] as [$email, $mdp, $nom, $fonction, $roles]) {
            $user = (new User())->setEmail($email)->setNom($nom)->setFonction($fonction)->setRoles($roles);
            $user->setPassword($this->hasher->hashPassword($user, $mdp));
            $manager->persist($user);
        }
    }

    private function chargerAptitudes(ObjectManager $manager): void
    {
        foreach ([
            'VOL' => 'Capacité de vol',
            'IMMUNITE_ENERGIE' => 'Immunité énergétique',
            'FORCE_STRUCTURELLE' => 'Force structurelle brute',
            'SUPPORT_MEDICAL' => 'Support évacuation médicale',
            'PIRATAGE' => 'Analyse technique & hack',
            'CRYOGENIE' => 'Cryokinésie',
            'NEGOCIATION' => 'Négociation d\'otages',
        ] as $code => $nom) {
            $this->aptitudes[$code] = new Aptitude($code, $nom);
            $manager->persist($this->aptitudes[$code]);
        }
    }

    private function chargerBonus(ObjectManager $manager): void
    {
        foreach ([
            ['ARMURE_NANO', 'Armure nano-composite', Statistique::VIGUEUR, 8],
            ['STIMULANT', 'Stimulant neural', Statistique::INTELLIGENCE, 6],
            ['PROPULSEURS', 'Propulseurs dorsaux', Statistique::MOBILITE, 10],
            ['ENTRAINEMENT_CQC', 'Entraînement corps-à-corps', Statistique::COMBAT, 5],
            ['MORAL', 'Moral d\'escouade', Statistique::GLOBAL, 3],
            ['COMMANDEMENT', 'Formation commandement', Statistique::CHARISME, 7],
        ] as [$code, $nom, $stat, $valeur]) {
            $this->bonus[$code] = (new Bonus())->setCode($code)->setNom($nom)->setStatistique($stat)->setValeurParDefaut($valeur);
            $manager->persist($this->bonus[$code]);
        }
    }

    private function chargerHeros(ObjectManager $manager, \DateTimeImmutable $now): void
    {
        // nom, alias, rang, rôle, spécialité, niveau, xp, pv, pvMax, [cbt, int, cha, mob, vig], x, y, secteur, fatigue, aptitudes
        $donnees = [
            ['Apex', 'Marcus Vane', 'ALPHA', HeroRole::ASSAUT, 'Manipulation cinétique & blindage d\'impact', 48, 14250, 470, 500, [95, 78, 88, 70, 92], 50.4, 49.6, 'Secteur QG-Genosha', 12, ['FORCE_STRUCTURELLE', 'IMMUNITE_ENERGIE']],
            ['Valkyrie', 'Elena Rostova', 'ALPHA', HeroRole::ECLAIREUR, 'Mobilité aérienne & supériorité', 42, 9800, 352, 400, [78, 72, 80, 96, 70], 38.0, 28.0, 'Couloir aérien Nord', 20, ['VOL']],
            ['Chronos', 'Dr. Liang', 'OMEGA', HeroRole::CONTROLE, 'Distorsion temporelle & analyse', 55, 3000, 288, 400, [62, 98, 74, 85, 60], 63.0, 42.0, 'Laboratoire quantique', 30, ['PIRATAGE']],
            ['Aura', 'Naomi Oka', 'BETA', HeroRole::SOUTIEN, 'Barrières psioniques & soins', 29, 4200, 114, 300, [40, 82, 90, 55, 50], 52.0, 51.0, 'Évacuation médicale', 65, ['SUPPORT_MEDICAL']],
            ['Titan', 'Brutus Cox', 'ALPHA', HeroRole::ASSAUT, 'Assaut lourd', 39, 6000, 425, 500, [97, 55, 60, 50, 98], 64.0, 44.0, 'Secteur Industriel 4', 40, ['FORCE_STRUCTURELLE']],
            ['Shadowstep', 'Kaï Moreau', 'BETA', HeroRole::ECLAIREUR, 'Infiltration & téléportation courte', 36, 5100, 260, 300, [74, 80, 58, 94, 55], 32.0, 70.0, 'Station Centrale', 25, ['NEGOCIATION']],
            ['Frost', 'Ingrid Hal', 'BETA', HeroRole::CONTROLE, 'Contrôle cryogénique de zone', 33, 2900, 240, 300, [70, 68, 62, 66, 75], 33.0, 71.0, 'Station Centrale', 22, ['CRYOGENIE']],
            ['Frostbite', 'Sven Kroll', 'BETA', HeroRole::CONTROLE, 'Cryokinésie moléculaire', 31, 1200, 300, 300, [72, 70, 50, 68, 80], 51.0, 48.5, 'Secteur QG-Genosha', 5, ['CRYOGENIE', 'IMMUNITE_ENERGIE']],
            ['Volt', 'Ada Nakamura', 'ALPHA', HeroRole::ECLAIREUR, 'Vitesse sonique & EMP', 44, 8800, 360, 400, [80, 88, 66, 97, 64], 76.0, 33.0, 'Secteur Industriel 4', 10, ['PIRATAGE', 'IMMUNITE_ENERGIE']],
            ['Ironhide', 'Gus Varga', 'BETA', HeroRole::ASSAUT, 'Vigueur colossale', 37, 400, 260, 400, [88, 45, 40, 42, 96], 74.0, 40.0, 'Dépôt Est', 61, ['FORCE_STRUCTURELLE']],
            ['Mirage', 'Lena Duval', 'BETA', HeroRole::SOUTIEN, 'Illusionniste psionique', 34, 3300, 294, 300, [55, 86, 92, 72, 52], 79.0, 38.0, 'Quartier Financier', 15, []],
            ['Nova', 'Iris Bell', 'ALPHA', HeroRole::ASSAUT, 'Projection plasma stellaire', 46, 11000, 420, 450, [92, 74, 70, 80, 78], 50.0, 50.5, 'Secteur QG-Genosha', 8, ['VOL', 'IMMUNITE_ENERGIE']],
            ['Bastion', 'Omar Saleh', 'BETA', HeroRole::SOUTIEN, 'Dôme de protection', 30, 2100, 180, 350, [60, 58, 76, 40, 90], 22.0, 62.0, 'Quai 7', 35, ['SUPPORT_MEDICAL']],
            ['Echo', 'Tomás Rey', 'BETA', HeroRole::ECLAIREUR, 'Écholocalisation & reconnaissance', 18, 900, 0, 250, [45, 66, 50, 82, 40], 49.0, 52.0, 'Infirmerie QG', 0, []],
        ];

        foreach ($donnees as [$nom, $alias, $rang, $role, $specialite, $niveau, $xp, $pv, $pvMax, $stats, $x, $y, $secteur, $fatigue, $aptitudes]) {
            $hero = (new Hero())
                ->setNom($nom)->setAlias($alias)->setRang($rang)->setRole($role)->setSpecialite($specialite)
                ->setNiveau($niveau)->setXp($xp)->setPointsDeVieMaximum($pvMax)->setPointsDeVie($pv)
                ->setCombat($stats[0])->setIntelligence($stats[1])->setCharisme($stats[2])->setMobilite($stats[3])->setVigueur($stats[4])
                ->setPositionX($x)->setPositionY($y)->setSecteur($secteur)->setFatigue($fatigue)
                ->setDescription(sprintf('%s, unité %s spécialisée : %s.', $nom, mb_strtolower($role->label()), mb_strtolower($specialite)));
            foreach ($aptitudes as $code) {
                $hero->addAptitude($this->aptitudes[$code]);
            }
            $manager->persist($hero);
            $this->heros[$nom] = $hero;
        }

        $this->heros['Aura']->setStatut(HeroStatut::BLESSE);
        $this->heros['Echo']->setStatut(HeroStatut::INDISPONIBLE);
        $this->heros['Bastion']->setStatut(HeroStatut::REPOS);

        // Bonus actifs, expirés et non cumulables
        $attribuer = function (string $hero, string $bonus, ?int $valeur, BonusOrigine $origine, bool $cumulable = true, ?string $expiration = null, bool $actif = true) use ($now): void {
            $hb = (new HeroBonus($this->bonus[$bonus], $valeur))
                ->setOrigine($origine)->setCumulable($cumulable)->setActif($actif)
                ->setDateDebut($now->modify('-3 days'))
                ->setDateExpiration(null !== $expiration ? $now->modify($expiration) : null);
            $this->heros[$hero]->addHeroBonus($hb);
        };
        $attribuer('Apex', 'ARMURE_NANO', null, BonusOrigine::EQUIPEMENT);
        $attribuer('Apex', 'COMMANDEMENT', null, BonusOrigine::ENTRAINEMENT, false);
        $attribuer('Valkyrie', 'PROPULSEURS', null, BonusOrigine::EQUIPEMENT, true, '+10 days');
        $attribuer('Chronos', 'STIMULANT', 4, BonusOrigine::MEDICAL, false, '+2 days');
        $attribuer('Titan', 'ENTRAINEMENT_CQC', null, BonusOrigine::ENTRAINEMENT);
        $attribuer('Volt', 'MORAL', null, BonusOrigine::MISSION, true, '+5 days');
        $attribuer('Nova', 'ARMURE_NANO', 5, BonusOrigine::EQUIPEMENT);
        $attribuer('Frostbite', 'STIMULANT', null, BonusOrigine::MEDICAL, true, '-1 day');
        $attribuer('Mirage', 'COMMANDEMENT', null, BonusOrigine::EVENEMENT, true, null, false);
    }

    private function chargerSynergies(ObjectManager $manager): void
    {
        foreach ([
            ['Vanguard-Time', 'Apex couvre pendant que Chronos ralentit la cible.', ['Apex', 'Chronos'], Statistique::GLOBAL, 8, null],
            ['Ciel & Acier', 'Valkyrie largue Apex au contact.', ['Apex', 'Valkyrie'], Statistique::MOBILITE, 10, null],
            ['Zéro Absolu', 'Frost et Frostbite figent une zone entière.', ['Frost', 'Frostbite'], Statistique::COMBAT, 12, null],
            ['Tempête Électro-Givre', 'EMP de Volt amplifié par la cryogénie.', ['Volt', 'Frostbite', 'Frost'], Statistique::INTELLIGENCE, 9, 2],
            ['Mur Vivant', 'Titan et Ironhide encaissent pour l\'escouade.', ['Titan', 'Ironhide'], Statistique::VIGUEUR, 10, null],
            ['Supernova', 'Nova et Apex : puissance de frappe maximale.', ['Nova', 'Apex'], Statistique::COMBAT, 8, null, Difficulte::A],
        ] as $ligne) {
            [$nom, $description, $heros, $stat, $valeur, $minimum] = $ligne;
            $synergie = (new Synergie())->setNom($nom)->setDescription($description)->setStatistique($stat)
                ->setValeurBonus($valeur)->setNombreMinimalHeros($minimum)->setDifficulteMinimale($ligne[6] ?? null);
            foreach ($heros as $hero) {
                $synergie->addHero($this->heros[$hero]);
            }
            $manager->persist($synergie);
        }
    }

    private function chargerVillains(ObjectManager $manager): void
    {
        foreach ([
            ['Titan d\'Or', 'Automate colossal alimenté au plasma instable.', 9, 92, 40, VillainStatut::LOCALISE],
            ['Magnaflux', 'Ingénieur renégat capable de surcharger les réacteurs.', 7, 70, 88, VillainStatut::LOCALISE],
            ['Cryo-Jack', 'Dissident de la Guilde Froid-Zéro, projections de givre.', 5, 66, 60, VillainStatut::EN_LIBERTE],
            ['Vipère Noire', 'Cheffe d\'un réseau de preneurs d\'otages.', 6, 58, 80, VillainStatut::LOCALISE],
            ['Le Sismographe', 'Provoque des anomalies sismiques ciblées.', 8, 60, 90, VillainStatut::EN_LIBERTE],
            ['Rouille', 'Corrode tout métal à distance.', 3, 40, 45, VillainStatut::CAPTURE],
        ] as [$nom, $description, $menace, $combat, $intelligence, $statut]) {
            $villain = (new Villain())->setNom($nom)->setDescription($description)->setNiveauMenace($menace)
                ->setCombat($combat)->setIntelligence($intelligence)->setStatut($statut);
            $manager->persist($villain);
            $this->villains[$nom] = $villain;
        }
    }

    /** @return array<string, Mission> */
    private function chargerMissions(ObjectManager $manager, \DateTimeImmutable $now): array
    {
        $missions = [];
        $creer = function (array $d) use ($manager, $now, &$missions): Mission {
            $m = (new Mission())
                ->setNom($d['nom'])->setDescription($d['description'])->setDifficulte($d['difficulte'])
                ->setTauxDeReussite(0)->setDateFinPrevue($now->modify($d['fin']))
                ->setCombatRequis($d['req'][0] ?? null)->setIntelligenceRequis($d['req'][1] ?? null)
                ->setCharismeRequis($d['req'][2] ?? 0)->setMobiliteRequis($d['req'][3] ?? null)->setVigueurRequis($d['req'][4] ?? null)
                ->setLocalisation($d['localisation'])->setPositionX($d['x'])->setPositionY($d['y'])
                ->setStatut($d['statut'])->setNiveauUrgence($d['urgence'])
                ->setTailleEquipeMinimale($d['taille'][0])->setTailleEquipeMaximale($d['taille'][1])
                ->setCible($d['cible'] ?? null)->setEtatCible($d['etatCible'] ?? null);
            if (isset($d['debut'])) {
                $m->setDateDebut($now->modify($d['debut']));
            }
            foreach ($d['villains'] ?? [] as $v) {
                $m->addVillain($this->villains[$v]);
            }
            foreach ($d['aptitudes'] ?? [] as $a) {
                $m->addAptitudeRequise($this->aptitudes[$a]);
            }
            foreach ($d['objectifs'] as $i => $o) {
                $m->addObjectif((new ObjectifMission())->setTitre($o[0])->setDescription($o[1])
                    ->setProgression($o[2])->setObligatoire($o[3] ?? true)->setOrdreAffichage($i + 1));
            }
            $manager->persist($m);
            $missions[$d['nom']] = $m;

            return $m;
        };

        $creer(['nom' => 'Opération Tempête d\'Acier', 'description' => 'Anomalie sismique dans le Quartier Portuaire : évacuer les quais et neutraliser le drone titanique.',
            'difficulte' => Difficulte::S, 'fin' => '+14 minutes', 'debut' => '-50 minutes', 'req' => [80, null, null, 60],
            'localisation' => 'Quartier Portuaire • Épicentre sismique', 'x' => 60.0, 'y' => 62.0, 'statut' => MissionStatut::EN_COURS,
            'urgence' => NiveauUrgence::CRITIQUE, 'taille' => [2, 4], 'villains' => ['Le Sismographe'],
            'cible' => 'Drone titanique', 'etatCible' => 'Bouclier entamé',
            'objectifs' => [
                ['Évacuer civils des quais', 'Zone A • 850 personnes redirigées', 100],
                ['Neutraliser drone titanique', 'Apex au contact direct • Bouclier entamé', 62],
                ['Sécuriser générateur énergétique', 'Requiert l\'arrivée de Valkyrie', 0],
            ]]);
        $creer(['nom' => 'Attaque du Réacteur Magna', 'description' => 'Secteur Industriel Beta • Vecteur d\'incursion lourd sur le réacteur principal.',
            'difficulte' => Difficulte::S, 'fin' => '+8 minutes 45 seconds', 'debut' => '-30 minutes', 'req' => [75, 70],
            'localisation' => 'Secteur Industriel Beta', 'x' => 66.0, 'y' => 45.0, 'statut' => MissionStatut::EN_COURS,
            'urgence' => NiveauUrgence::CRITIQUE, 'taille' => [2, 3], 'villains' => ['Magnaflux'],
            'objectifs' => [
                ['Couper l\'alimentation secondaire', 'Chronos sur la console de contrôle', 80],
                ['Repousser l\'incursion', 'Titan en première ligne', 45],
                ['Stabiliser le cœur du réacteur', null, 10],
            ]]);
        $creer(['nom' => 'Prise d\'otages Métro Ligne 4', 'description' => 'Station Centrale souterraine • 140 civils protégés.',
            'difficulte' => Difficulte::B, 'fin' => '+22 minutes 10 seconds', 'debut' => '-15 minutes', 'req' => [null, 60, 50],
            'localisation' => 'Station Centrale Souterraine', 'x' => 34.0, 'y' => 72.0, 'statut' => MissionStatut::EN_COURS,
            'urgence' => NiveauUrgence::HAUTE, 'taille' => [2, 3], 'villains' => ['Vipère Noire'], 'aptitudes' => ['NEGOCIATION'],
            'objectifs' => [
                ['Établir le contact', 'Canal de négociation ouvert', 100],
                ['Isoler les ravisseurs', 'Shadowstep en infiltration', 55],
                ['Évacuer les otages', null, 0],
                ['Sécuriser la rame 4B', null, 0, false],
            ]]);
        $creer(['nom' => 'Incendie Chimique Docks Est', 'description' => 'Zone Portuaire Sud • Risque d\'explosion toxique.',
            'difficulte' => Difficulte::S, 'fin' => '+40 minutes', 'req' => [null, null, null, null, 70],
            'localisation' => 'Zone Portuaire Sud', 'x' => 51.0, 'y' => 49.0, 'statut' => MissionStatut::EN_ATTENTE,
            'urgence' => NiveauUrgence::CRITIQUE, 'taille' => [2, 4], 'aptitudes' => ['SUPPORT_MEDICAL'],
            'objectifs' => [
                ['Confiner le nuage toxique', null, 0],
                ['Refroidir les cuves', null, 0],
                ['Évacuer le personnel des docks', null, 0],
            ]]);
        $creer(['nom' => 'Neutralisation du Titan d\'Or — Secteur Industriel 04', 'description' => 'Un automate colossal alimenté au plasma instable progresse vers la raffinerie principale. Requiert une force brute combinée à une intelligence tactique pour désactiver les valves de refroidissement avant ignition critique du cœur thermonucléaire.',
            'difficulte' => Difficulte::S, 'fin' => '+45 minutes', 'req' => [85, null, null, 70],
            'localisation' => 'Secteur Industriel 04 • Grid 44-XN', 'x' => 72.0, 'y' => 36.0, 'statut' => MissionStatut::EN_ATTENTE,
            'urgence' => NiveauUrgence::CRITIQUE, 'taille' => [3, 4], 'villains' => ['Titan d\'Or'], 'aptitudes' => ['IMMUNITE_ENERGIE'],
            'cible' => 'Titan d\'Or', 'etatCible' => 'Surchauffe 784°C (+12°/min)',
            'objectifs' => [
                ['Intercepter l\'automate', null, 0],
                ['Désactiver les valves de refroidissement', null, 0],
                ['Protéger la raffinerie', null, 0, false],
            ]]);
        $creer(['nom' => 'Braquage Banque Centrale', 'description' => 'Suspicion de braquage métahumain avec projections cryogéniques.',
            'difficulte' => Difficulte::A, 'fin' => '+2 hours', 'req' => [70, 60],
            'localisation' => 'Quartier Financier • Avenue des Titans', 'x' => 80.0, 'y' => 40.0, 'statut' => MissionStatut::BROUILLON,
            'urgence' => NiveauUrgence::HAUTE, 'taille' => [2, 3], 'villains' => ['Cryo-Jack'],
            'objectifs' => [
                ['Boucler le périmètre', null, 0],
                ['Identifier les suspects', null, 0],
            ]]);
        $terminee = $creer(['nom' => 'Démantèlement Guilde Froid-Zéro', 'description' => 'Raid sur l\'entrepôt principal de la guilde.',
            'difficulte' => Difficulte::A, 'fin' => '-2 days', 'debut' => '-2 days 3 hours', 'req' => [70],
            'localisation' => 'Entrepôts Nord', 'x' => 30.0, 'y' => 20.0, 'statut' => MissionStatut::TERMINEE,
            'urgence' => NiveauUrgence::MOYENNE, 'taille' => [2, 4],
            'objectifs' => [
                ['Neutraliser les gardes', null, 100],
                ['Saisir le stock cryogénique', null, 100],
            ]]);
        $terminee->setDateFinReelle(new \DateTimeImmutable('-2 days 1 hour'));
        $echouee = $creer(['nom' => 'Évasion Prison Bastille-9', 'description' => 'Tentative d\'évasion massive.',
            'difficulte' => Difficulte::B, 'fin' => '-5 days', 'debut' => '-5 days 2 hours',
            'localisation' => 'Bastille-9', 'x' => 15.0, 'y' => 85.0, 'statut' => MissionStatut::ECHOUEE,
            'urgence' => NiveauUrgence::HAUTE, 'taille' => [1, 3],
            'objectifs' => [
                ['Verrouiller les blocs', null, 70],
                ['Reprendre les fugitifs', null, 30],
            ]]);
        $echouee->setDateFinReelle(new \DateTimeImmutable('-5 days 1 hour'));
        $creer(['nom' => 'Patrouille Couloir Aérien Nord', 'description' => 'Surveillance préventive du couloir aérien.',
            'difficulte' => Difficulte::D, 'fin' => '+6 hours',
            'localisation' => 'Couloir aérien Nord', 'x' => 40.0, 'y' => 12.0, 'statut' => MissionStatut::EN_ATTENTE,
            'urgence' => NiveauUrgence::FAIBLE, 'taille' => [1, 2], 'aptitudes' => ['VOL'],
            'objectifs' => [
                ['Survoler le secteur 1', null, 0],
                ['Survoler le secteur 2', null, 0],
            ]]);

        // Affectations actives
        $affecter = function (Mission $mission, array $heros, AffectationStatut $statut, ?string $date = null) use ($manager, $now): void {
            foreach ($heros as $i => $nom) {
                $hero = $this->heros[$nom];
                $affectation = (new AffectationMission($hero, $mission, $now->modify($date ?? '-20 minutes')))
                    ->setRole(0 === $i ? RoleEquipe::LEAD : RoleEquipe::MEMBRE)
                    ->setStatut($statut);
                if (AffectationStatut::TERMINEE === $statut) {
                    $affectation->setContribution((int) round(100 / \count($heros)))->setDateArrivee($now->modify($date ?? '-20 minutes')->modify('+10 minutes'));
                }
                $mission->addAffectation($affectation);
                $hero->addAffectation($affectation);
                $manager->persist($affectation);
                if ($statut->isActive()) {
                    $hero->setStatut(HeroStatut::EN_MISSION);
                }
            }
            $mission->setTauxDeReussite($this->calculator->taux($mission, array_map(fn ($n) => $this->heros[$n], $heros)));
        };
        $affecter($missions['Opération Tempête d\'Acier'], ['Apex', 'Valkyrie'], AffectationStatut::SUR_PLACE);
        $affecter($missions['Attaque du Réacteur Magna'], ['Titan', 'Chronos'], AffectationStatut::SUR_PLACE);
        $affecter($missions['Prise d\'otages Métro Ligne 4'], ['Shadowstep', 'Frost'], AffectationStatut::AFFECTE);
        $affecter($terminee, ['Frostbite', 'Nova', 'Ironhide'], AffectationStatut::TERMINEE, '-2 days 3 hours');
        $affecter($echouee, ['Bastion', 'Echo'], AffectationStatut::TERMINEE, '-5 days 2 hours');

        foreach ($missions as $mission) {
            if (!$mission->getStatut()->isClosed() && 0 === \count($mission->getAffectationsActives())) {
                $mission->setTauxDeReussite(0);
            }
        }

        $alerte = new Alerte('URGENCE ALPHA', 'Anomalie sismique secteur Quartier Portuaire • Menace niveau 4 • Intervention immédiate requise', NiveauAlerte::CRITIQUE, 'SEISM-904-GX', $now->modify('-50 minutes'));
        $alerte->setMission($missions['Opération Tempête d\'Acier']);
        $manager->persist($alerte);
        $alerte = new Alerte('Mission critique', 'Neutralisation du Titan d\'Or • Secteur Industriel 04 • Rang S', NiveauAlerte::CRITIQUE, 'MIS-TITAN-04', $now->modify('-10 minutes'));
        $alerte->setMission($missions['Neutralisation du Titan d\'Or — Secteur Industriel 04']);
        $manager->persist($alerte);

        return $missions;
    }

    /** @param array<string, Mission> $missions */
    private function chargerRequetes(ObjectManager $manager, \DateTimeImmutable $now, array $missions): void
    {
        $donnees = [
            ['Effondrement partiel du Pont Saint-Martin avec véhicules suspendus', 'Deux autobus scolaires bloqués à l\'extrémité du tablier fissuré. Rupture estimée dans moins de 8 minutes sans stabilisation cinétique. ~60 civils en danger imminent.', 'Secteur Nord-Est • Pylône Sud B-12', 70.0, 18.0, NiveauUrgence::CRITIQUE, RequeteCategorie::URGENCE_VITALE, OrigineAlerte::POLICE, 'Police Centrale', '-4 minutes', RequeteStatut::NOUVELLE],
            ['Suspicion de braquage métahumain – Banque Centrale de Genosha', 'Témoin oculaire : projections de givre cryogénique bloquant le sas principal et arcs lasers perforateurs. Deux individus armés vêtus d\'armures pressurisées.', 'Quartier Financier • Avenue des Titans', 80.0, 40.0, NiveauUrgence::HAUTE, RequeteCategorie::SUPER_VILAIN, OrigineAlerte::CAPTEUR, 'Alarme automatisée Banque', '-14 minutes', RequeteStatut::CONVERTIE],
            ['Chat géant mutant coincé sur l\'antenne relais télécom', 'Le félin mesure environ 3,5 m de long suite à une ingestion accidentelle de résidus bio-énergétiques. Perturbation de 15 % du réseau 5G local.', 'Colline des Chênes • Tour Telecom T-8', 20.0, 30.0, NiveauUrgence::FAIBLE, RequeteCategorie::ASSISTANCE_PUBLIQUE, OrigineAlerte::CIVIL, 'Riverain via App', '-32 minutes', RequeteStatut::EN_QUALIFICATION],
            ['Anomalie sismique Quartier Portuaire', 'Secousses répétées et fissures sur les quais.', 'Quartier Portuaire • Quai 4', 60.0, 62.0, NiveauUrgence::CRITIQUE, RequeteCategorie::CATASTROPHE, OrigineAlerte::CAPTEUR, 'Réseau sismique', '-55 minutes', RequeteStatut::CONVERTIE],
            ['Fuite chimique aux Docks Est', 'Odeur âcre et nuage jaunâtre au-dessus des cuves.', 'Zone Portuaire Sud', 51.0, 49.0, NiveauUrgence::CRITIQUE, RequeteCategorie::CATASTROPHE, OrigineAlerte::POMPIERS, 'Caserne 12', '-20 minutes', RequeteStatut::CONVERTIE],
            ['Coupure de courant Secteur 7', 'Activité anormale sur le réseau électrique, quartier plongé dans le noir.', 'Secteur 7 • Poste source', 45.0, 35.0, NiveauUrgence::MOYENNE, RequeteCategorie::ASSISTANCE_PUBLIQUE, OrigineAlerte::CIVIL, 'Mme Dupuis', '-1 hour', RequeteStatut::NOUVELLE],
            ['Graffitis animés sur la mairie', 'Les graffitis bougent et insultent les passants.', 'Place de l\'Hôtel de Ville', 48.0, 55.0, NiveauUrgence::FAIBLE, RequeteCategorie::ASSISTANCE_PUBLIQUE, OrigineAlerte::CIVIL, 'M. Leroy', '-3 hours', RequeteStatut::CLASSEE],
            ['Individu volant au-dessus de l\'autoroute A9', 'Silhouette en armure survolant le trafic à basse altitude.', 'Autoroute A9 • Sortie 14', 85.0, 70.0, NiveauUrgence::MOYENNE, RequeteCategorie::SUPER_VILAIN, OrigineAlerte::POLICE, 'Patrouille A9', '-2 hours', RequeteStatut::EN_QUALIFICATION],
            ['Inondation du tunnel Corniche', 'Canalisation rompue, trois véhicules bloqués.', 'Tunnel de la Corniche', 25.0, 78.0, NiveauUrgence::HAUTE, RequeteCategorie::URGENCE_VITALE, OrigineAlerte::POMPIERS, 'Caserne 4', '-25 minutes', RequeteStatut::NOUVELLE],
            ['Disparition d\'un scientifique du Labo Quantique', 'Le Dr. Ferrand n\'a pas donné signe de vie depuis 24 h.', 'Laboratoire quantique', 63.0, 42.0, NiveauUrgence::MOYENNE, RequeteCategorie::SUPER_VILAIN, OrigineAlerte::CIVIL, 'Direction du Labo', '-6 hours', RequeteStatut::NOUVELLE],
            ['Prise d\'otages station Centrale', 'Un groupe armé retient les passagers de la rame 4B.', 'Station Centrale Souterraine', 34.0, 72.0, NiveauUrgence::HAUTE, RequeteCategorie::URGENCE_VITALE, OrigineAlerte::POLICE, 'Police des transports', '-18 minutes', RequeteStatut::CONVERTIE],
        ];
        $liens = [
            'Suspicion de braquage métahumain – Banque Centrale de Genosha' => 'Braquage Banque Centrale',
            'Anomalie sismique Quartier Portuaire' => 'Opération Tempête d\'Acier',
            'Fuite chimique aux Docks Est' => 'Incendie Chimique Docks Est',
            'Prise d\'otages station Centrale' => 'Prise d\'otages Métro Ligne 4',
        ];

        foreach ($donnees as [$titre, $description, $adresse, $x, $y, $urgence, $categorie, $origine, $demandeur, $quand, $statut]) {
            $requete = (new RequeteCitoyenne())
                ->setTitre($titre)->setDescription($description)->setAdresse($adresse)
                ->setPositionX($x)->setPositionY($y)->setNiveauUrgence($urgence)->setCategorie($categorie)
                ->setOrigine($origine)->setNomDemandeur($demandeur)->setDateCreation($now->modify($quand))->setStatut($statut);
            if (isset($liens[$titre])) {
                $requete->setMission($missions[$liens[$titre]]);
            }
            $manager->persist($requete);

            if (NiveauUrgence::CRITIQUE === $urgence && RequeteStatut::NOUVELLE === $statut) {
                $alerte = new Alerte('URGENCE ALPHA', sprintf('%s • %s', $titre, $adresse), NiveauAlerte::CRITIQUE, 'REQ-PONT-B12', $now->modify($quand));
                $alerte->setRequete($requete);
                $manager->persist($alerte);
            }
        }
    }
}
