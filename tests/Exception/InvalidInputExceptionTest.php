<?php

declare(strict_types=1);

namespace Rak200\HttpInput\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Rak200\HttpInput\Exception\InputException;
use Rak200\HttpInput\Exception\InvalidInputException;

/**
 * @internal
 *
 * @coversNothing
 */
final class InvalidInputExceptionTest extends TestCase
{
    public function testIsAnInputException(): void
    {
        $exception = new InvalidInputException('must be an integer');

        $this->assertInstanceOf(InputException::class, $exception);
        $this->assertSame('must be an integer', $exception->getMessage());
    }
}
