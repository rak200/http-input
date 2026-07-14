<?php

declare(strict_types=1);

namespace Rak200\HttpInput\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Rak200\HttpInput\Exception\InputException;
use Rak200\HttpInput\Exception\InvalidInputException;
use Rak200\HttpInput\Exception\LengthInputException;

/**
 * @internal
 *
 * @coversNothing
 */
final class LengthInputExceptionTest extends TestCase
{
    public function testExtendsInvalidInputException(): void
    {
        $exception = new LengthInputException('must be at least 2 characters');

        $this->assertInstanceOf(InvalidInputException::class, $exception);
        $this->assertInstanceOf(InputException::class, $exception);
        $this->assertSame('must be at least 2 characters', $exception->getMessage());
    }
}
