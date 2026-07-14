<?php

declare(strict_types=1);

namespace Rak200\HttpInput\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Rak200\HttpInput\Exception\InputException;
use Rak200\HttpInput\Exception\InvalidInputException;
use Rak200\HttpInput\Exception\OutOfRangeInputException;

/**
 * @internal
 *
 * @coversNothing
 */
final class OutOfRangeInputExceptionTest extends TestCase
{
    public function testExtendsInvalidInputException(): void
    {
        $exception = new OutOfRangeInputException('must be at least 1');

        $this->assertInstanceOf(InvalidInputException::class, $exception);
        $this->assertInstanceOf(InputException::class, $exception);
        $this->assertSame('must be at least 1', $exception->getMessage());
    }
}
