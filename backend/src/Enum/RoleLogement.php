<?php

namespace App\Enum;

enum RoleLogement: string
{
    case COPROPRIETAIRE = 'COPROPRIETAIRE';
    case MINEUR = 'MINEUR';

    public function label(): string
    {
        return match ($this) {
            self::COPROPRIETAIRE => 'Co-propriétaire',
            self::MINEUR         => 'Membre mineur',
        };
    }
}
