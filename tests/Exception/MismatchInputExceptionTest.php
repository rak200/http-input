<?php

declare(strict_types=1);

namespace Rak200\HttpInput\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Rak200\HttpInput\Exception\InputException;
use Rak200\HttpInput\Exception\InvalidInputException;
use Rak200\HttpInput\Exception\MismatchInputException;

/**
 * @internal
 *
 * @coversNothing
 */
final class MismatchInputExceptionTest extends TestCase
{
    public function testExtendsInvalidInputException(): void
    {
        $exception = new MismatchInputException('must match');

        $this->assertInstanceOf(InvalidInputException::class, $exception);
        $this->assertInstanceOf(InputException::class, $exception);
        $this->assertSame('must match', $exception->getMessage());
    }
}
