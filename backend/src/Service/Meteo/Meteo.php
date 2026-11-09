<?php

namespace App\Service\Meteo;

/**
 * Météo du moment à la position de l'utilisateur.
 */
final class Meteo
{
    public function __construct(
        public readonly ?string $ville,
        public readonly float $temperature,
        public readonly bool $pluie,
        public readonly string $libelle,
    ) {
    }

    public function estFroid(): bool
    {
        return $this->temperature < 12;
    }

    public function estChaud(): bool
    {
        return $this->temperature >= 22;
    }
}
