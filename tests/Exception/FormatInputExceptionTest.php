<?php

declare(strict_types=1);

namespace Rak200\HttpInput\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Rak200\HttpInput\Exception\FormatInputException;
use Rak200\HttpInput\Exception\InputException;
use Rak200\HttpInput\Exception\InvalidInputException;

/**
 * @internal
 *
 * @coversNothing
 */
final class FormatInputExceptionTest extends TestCase
{
    public function testExtendsInvalidInputException(): void
    {
        $exception = new FormatInputException('must be a valid e-mail');

        $this->assertInstanceOf(InvalidInputException::class, $exception);
        $this->assertInstanceOf(InputException::class, $exception);
        $this->assertSame('must be a valid e-mail', $exception->getMessage());
    }
}
