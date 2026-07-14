<?php

declare(strict_types=1);

namespace Rak200\HttpInput\Tests;

use PHPUnit\Framework\TestCase;
use Rak200\HttpInput\Exception\OutOfRangeInputException;
use Rak200\HttpInput\Violation;

/**
 * @internal
 *
 * @coversNothing
 */
final class ViolationTest extends TestCase
{
    public function testHoldsMessageWithNoExceptionSubtypeByDefault(): void
    {
        $violation = new Violation('must be even');

        $this->assertSame('must be even', $violation->message);
        $this->assertNull($violation->exceptionClass);
    }

    public function testHoldsTheExceptionSubtype(): void
    {
        $violation = new Violation('must be at least 1', OutOfRangeInputException::class);

        $this->assertSame(OutOfRangeInputException::class, $violation->exceptionClass);
    }
}
