<?php

namespace App\Geo\Geocoding;

use App\Geo\Coordonnees;

final readonly class ResultatGeocodage
{
    public function __construct(
        public Coordonnees $coordonnees,
        public string $libelle,
        public ?string $codePostal,
        public ?string $ville,
        public string $fournisseur,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'lat' => $this->coordonnees->latitude,
            'lng' => $this->coordonnees->longitude,
            'libelle' => $this->libelle,
            'codePostal' => $this->codePostal,
            'ville' => $this->ville,
            'fournisseur' => $this->fournisseur,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            new Coordonnees((float) $data['lat'], (float) $data['lng']),
            (string) $data['libelle'],
            $data['codePostal'] ?? null,
            $data['ville'] ?? null,
            (string) $data['fournisseur'],
        );
    }
}
