<?php

namespace App\Form\Model;

use App\Entity\Mission;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/** Position réelle d'une mission : adresse à géocoder, ou coordonnées saisies. */
final class MissionPosition
{
    #[Assert\Length(max: 200)]
    public ?string $adresse = null;

    #[Assert\Length(max: 10)]
    public ?string $codePostal = null;

    #[Assert\Length(max: 120)]
    public ?string $ville = null;

    #[Assert\Range(min: -90, max: 90)]
    public ?float $latitude = null;

    #[Assert\Range(min: -180, max: 180)]
    public ?float $longitude = null;

    public static function depuis(Mission $mission): self
    {
        $p = new self();
        $geo = $mission->getGeoPosition();
        $p->adresse = $geo?->getAdresseSaisie() ?? $mission->getLocalisation();
        $p->codePostal = $geo?->getCodePostal();
        $p->ville = $geo?->getVille();
        $c = $mission->getCoordonnees();
        $p->latitude = $c?->latitude;
        $p->longitude = $c?->longitude;

        return $p;
    }

    #[Assert\Callback]
    public function valider(ExecutionContextInterface $context): void
    {
        if ((null === $this->latitude) !== (null === $this->longitude)) {
            $context->buildViolation('Renseignez la latitude et la longitude, ou aucune des deux.')->atPath('latitude')->addViolation();
        }
        if (null === $this->latitude && (null === $this->adresse || '' === trim($this->adresse))) {
            $context->buildViolation('Indiquez une adresse à géocoder ou des coordonnées.')->atPath('adresse')->addViolation();
        }
    }
}
