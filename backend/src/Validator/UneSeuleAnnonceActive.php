<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class UneSeuleAnnonceActive extends Constraint
{
    public string $message = 'Cet article a déjà une annonce en ligne ou réservée.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
