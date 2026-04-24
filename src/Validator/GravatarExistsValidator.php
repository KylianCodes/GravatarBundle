<?php

declare(strict_types=1);

namespace KylianCodes\GravatarBundle\Validator;

use KylianCodes\GravatarBundle\Service\GravatarService;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Validates that an email address has an associated Gravatar account.
 */
class GravatarExistsValidator extends ConstraintValidator
{
    public function __construct(private readonly GravatarService $gravatarService)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof GravatarExists) {
            throw new UnexpectedTypeException($constraint, GravatarExists::class);
        }

        if ($value === null || $value === '') {
            return;
        }

        if (!\is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        if (!$this->gravatarService->exists($value)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $value)
                ->addViolation();
        }
    }
}
