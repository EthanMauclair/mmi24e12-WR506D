<?php

namespace App\Service\CodeBarres;

final class BaseInjoignableException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Les bases de livres et de disques ne répondent pas pour le moment. Réessayez dans quelques secondes.');
    }
}
