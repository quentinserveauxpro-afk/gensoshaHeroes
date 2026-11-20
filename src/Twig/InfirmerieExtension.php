<?php

namespace App\Twig;

use App\Entity\Hero;
use App\Entity\SejourInfirmerie;
use App\Enum\MotifInfirmerie;
use App\Infirmerie\ConsequencesRetrait;
use App\Infirmerie\HeroMedicalEvaluationService;
use App\Infirmerie\HeroRepatriationService;
use App\Infirmerie\InfirmerieConfiguration;
use App\Repository\SejourInfirmerieRepository;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

final class InfirmerieExtension
{
    public function __construct(
        private readonly SejourInfirmerieRepository $sejours,
        private readonly HeroRepatriationService $rapatriement,
        private readonly HeroMedicalEvaluationService $medical,
        private readonly InfirmerieConfiguration $config,
    ) {
    }

    #[AsTwigFunction('sejour_actif')]
    public function sejourActif(Hero $hero): ?SejourInfirmerie
    {
        return null === $hero->getId() ? null : $this->sejours->findActifPourHero($hero);
    }

    #[AsTwigFunction('infirmerie_consequences')]
    public function consequences(Hero $hero): ConsequencesRetrait
    {
        return $this->rapatriement->consequences($hero);
    }

    /** @return list<MotifInfirmerie> */
    #[AsTwigFunction('infirmerie_motifs')]
    public function motifs(): array
    {
        return MotifInfirmerie::manuels();
    }

    #[AsTwigFunction('infirmerie_config')]
    public function config(): InfirmerieConfiguration
    {
        return $this->config;
    }

    #[AsTwigFunction('etat_medical')]
    public function etatMedical(Hero $hero): ?string
    {
        return match (true) {
            $this->medical->estSanteCritique($hero) => 'Santé critique',
            $this->medical->estFatigueCritique($hero) => 'Épuisement critique',
            default => null,
        };
    }

    /**
     * Minuterie « MM:SS » (09:42, 00:08) ; « H:MM:SS » au-delà d'une heure ; tiret si inconnue.
     * Jamais négative, jamais NaN ni infinie.
     */
    #[AsTwigFilter('minuterie')]
    public static function minuterie(int|float|null $secondes): string
    {
        if (null === $secondes || !is_finite((float) $secondes)) {
            return '—';
        }
        $s = max(0, (int) round($secondes));

        return $s >= 3600
            ? sprintf('%d:%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60), $s % 60)
            : sprintf('%02d:%02d', intdiv($s, 60), $s % 60);
    }

    /** Durée lisible (« 2 h 05 min », « 45 s ») ; tiret si inconnue. */
    #[AsTwigFilter('duree_lisible')]
    public static function dureeLisible(?int $secondes): string
    {
        if (null === $secondes) {
            return '—';
        }
        $secondes = max(0, $secondes);
        $h = intdiv($secondes, 3600);
        $m = intdiv($secondes % 3600, 60);

        return match (true) {
            $h >= 24 => sprintf('%d j %02d h', intdiv($h, 24), $h % 24),
            $h > 0 => sprintf('%d h %02d min', $h, $m),
            $m > 0 => sprintf('%d min', $m),
            default => sprintf('%d s', $secondes),
        };
    }
}
