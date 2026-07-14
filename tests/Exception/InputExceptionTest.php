<?php

declare(strict_types=1);

namespace Rak200\HttpInput\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Rak200\HttpInput\Exception\InputException;
use ReflectionClass;
use RuntimeException;

/**
 * @internal
 *
 * @coversNothing
 */
final class InputExceptionTest extends TestCase
{
    public function testIsAbstract(): void
    {
        $this->assertTrue(new ReflectionClass(InputException::class)->isAbstract());
    }

    public function testExtendsRuntimeException(): void
    {
        $parent = new ReflectionClass(InputException::class)->getParentClass();

        $this->assertNotFalse($parent);
        $this->assertSame(RuntimeException::class, $parent->getName());
    }
}
