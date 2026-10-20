<?php

namespace App\Validator;

use App\Entity\Annonce;
use App\Repository\AnnonceRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class UneSeuleAnnonceActiveValidator extends ConstraintValidator
{
    public function __construct(private readonly AnnonceRepository $annonceRepository)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UneSeuleAnnonceActive) {
            throw new UnexpectedTypeException($constraint, UneSeuleAnnonceActive::class);
        }

        if (!$value instanceof Annonce) {
            throw new UnexpectedValueException($value, Annonce::class);
        }

        if (!$value->estActive() || null === $value->getArticle()) {
            return;
        }

        if ($this->annonceRepository->existeAutreAnnonceActive($value)) {
            $this->context->buildViolation($constraint->message)
                ->atPath('article')
                ->addViolation();
        }
    }
}
