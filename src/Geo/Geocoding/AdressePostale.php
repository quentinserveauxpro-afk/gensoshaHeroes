<?php

namespace App\Geo\Geocoding;

/** Adresse à géocoder (France métropolitaine et outre-mer). */
final readonly class AdressePostale
{
    public function __construct(
        public string $ligne,
        public ?string $codePostal = null,
        public ?string $ville = null,
        public ?string $complement = null,
        public string $pays = 'France',
    ) {
    }

    /** Requête libre transmise au fournisseur (le complément d'adresse n'aide pas le géocodage). */
    public function versRequete(): string
    {
        return implode(', ', array_filter([
            trim($this->ligne),
            trim(($this->codePostal ?? '').' '.($this->ville ?? '')),
            $this->pays,
        ], static fn (string $p) => '' !== $p));
    }

    /** Clé de cache insensible à la casse, aux accents et aux espaces multiples. */
    public function cleNormalisee(): string
    {
        $texte = mb_strtolower($this->versRequete());
        $texte = (string) preg_replace('/\s+/u', ' ', $texte);
        if (class_exists(\Transliterator::class)) {
            $texte = (string) \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC')?->transliterate($texte);
        }

        return hash('sha256', trim($texte));
    }
}
