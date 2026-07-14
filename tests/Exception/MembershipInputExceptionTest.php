<?php

declare(strict_types=1);

namespace Rak200\HttpInput\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Rak200\HttpInput\Exception\InputException;
use Rak200\HttpInput\Exception\InvalidInputException;
use Rak200\HttpInput\Exception\MembershipInputException;

/**
 * @internal
 *
 * @coversNothing
 */
final class MembershipInputExceptionTest extends TestCase
{
    public function testExtendsInvalidInputException(): void
    {
        $exception = new MembershipInputException('must be one of s, m, l');

        $this->assertInstanceOf(InvalidInputException::class, $exception);
        $this->assertInstanceOf(InputException::class, $exception);
        $this->assertSame('must be one of s, m, l', $exception->getMessage());
    }
}
