<?php

declare(strict_types=1);

namespace Rak200\HttpInput\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Rak200\HttpInput\Exception\InputException;
use Rak200\HttpInput\Exception\MissingInputException;

/**
 * @internal
 *
 * @coversNothing
 */
final class MissingInputExceptionTest extends TestCase
{
    public function testIsAnInputException(): void
    {
        $exception = new MissingInputException('key "page" is missing');

        $this->assertInstanceOf(InputException::class, $exception);
        $this->assertSame('key "page" is missing', $exception->getMessage());
    }
}
