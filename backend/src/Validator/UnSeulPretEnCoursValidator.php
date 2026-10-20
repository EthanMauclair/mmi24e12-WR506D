<?php

namespace App\Validator;

use App\Entity\Pret;
use App\Repository\PretRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class UnSeulPretEnCoursValidator extends ConstraintValidator
{
    public function __construct(private readonly PretRepository $pretRepository)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UnSeulPretEnCours) {
            throw new UnexpectedTypeException($constraint, UnSeulPretEnCours::class);
        }

        if (!$value instanceof Pret) {
            throw new UnexpectedValueException($value, Pret::class);
        }

        // Un article pas encore enregistré n'a forcément aucun prêt
        if (!$value->estEnCours() || null === $value->getArticle()?->getId()) {
            return;
        }

        if ($this->pretRepository->existeAutrePretEnCours($value)) {
            $this->context->buildViolation($constraint->message)
                ->atPath('article')
                ->addViolation();
        }
    }
}
