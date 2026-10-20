<?php

namespace App\Enum;

enum StatutAnnonce: string
{
    case EN_LIGNE = 'EN_LIGNE';
    case RESERVEE = 'RESERVEE';
    case VENDUE = 'VENDUE';
    case RETIREE = 'RETIREE';

    public function label(): string
    {
        return match ($this) {
            self::EN_LIGNE => 'En ligne',
            self::RESERVEE => 'Réservée',
            self::VENDUE   => 'Vendue',
            self::RETIREE  => 'Retirée',
        };
    }
}
