<?php

declare(strict_types=1);

namespace KylianCodes\GravatarBundle\Tests\Validator;

use KylianCodes\GravatarBundle\Service\GravatarService;
use KylianCodes\GravatarBundle\Validator\GravatarExists;
use KylianCodes\GravatarBundle\Validator\GravatarExistsValidator;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class GravatarExistsValidatorTest extends ConstraintValidatorTestCase
{
    private GravatarService&MockObject $gravatarService;

    protected function createValidator(): ConstraintValidatorInterface
    {
        $this->gravatarService = $this->createMock(GravatarService::class);

        return new GravatarExistsValidator($this->gravatarService);
    }

    public function testValidEmailWithGravatarPassesValidation(): void
    {
        $this->gravatarService->method('exists')->willReturn(true);

        $this->validator->validate('test@example.com', new GravatarExists());

        $this->assertNoViolation();
    }

    public function testEmailWithoutGravatarAddsViolation(): void
    {
        $this->gravatarService->method('exists')->willReturn(false);

        $this->validator->validate('noavatar@example.com', new GravatarExists());

        $this->buildViolation('The email "{{ value }}" does not have a Gravatar account.')
            ->setParameter('{{ value }}', 'noavatar@example.com')
            ->assertRaised();
    }

    public function testNullValueSkipsValidation(): void
    {
        $this->gravatarService->expects($this->never())->method('exists');

        $this->validator->validate(null, new GravatarExists());

        $this->assertNoViolation();
    }

    public function testEmptyStringSkipsValidation(): void
    {
        $this->gravatarService->expects($this->never())->method('exists');

        $this->validator->validate('', new GravatarExists());

        $this->assertNoViolation();
    }

    public function testNonStringValueThrowsException(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $this->validator->validate(12345, new GravatarExists());
    }

    public function testWrongConstraintTypeThrowsException(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $wrongConstraint = $this->createMock(Constraint::class);
        $this->validator->validate('test@example.com', $wrongConstraint);
    }
}
