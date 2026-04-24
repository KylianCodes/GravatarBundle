<?php

declare(strict_types=1);

namespace KylianCodes\GravatarBundle\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Constraint that checks whether an email has a Gravatar account.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
class GravatarExists extends Constraint
{
    public string $message = 'The email "{{ value }}" does not have a Gravatar account.';
}
