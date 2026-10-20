<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class UnSeulPretEnCours extends Constraint
{
    public string $message = 'Cet article est déjà prêté.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
