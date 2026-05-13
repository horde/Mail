<?php

declare(strict_types=1);

namespace Horde\Mail\Test\Unit\Rfc822;

use PHPUnit\Framework\Attributes\CoversClass;
use Horde\Mail\Rfc822\ValidationMode;
use PHPUnit\Framework\TestCase;

#[CoversClass(ValidationMode::class)]
class ValidationModeTest extends TestCase
{
    public function testCasesExist(): void
    {
        $cases = ValidationMode::cases();
        $this->assertCount(3, $cases);
    }

    public function testLenient(): void
    {
        $this->assertSame('Lenient', ValidationMode::Lenient->name);
    }

    public function testStrict(): void
    {
        $this->assertSame('Strict', ValidationMode::Strict->name);
    }

    public function testEai(): void
    {
        $this->assertSame('Eai', ValidationMode::Eai->name);
    }

    public function testCasesAreDistinct(): void
    {
        $this->assertNotSame(ValidationMode::Lenient, ValidationMode::Strict);
        $this->assertNotSame(ValidationMode::Strict, ValidationMode::Eai);
        $this->assertNotSame(ValidationMode::Lenient, ValidationMode::Eai);
    }
}
