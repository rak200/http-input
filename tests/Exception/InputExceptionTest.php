<?php

declare(strict_types=1);

namespace Rak200\HttpInput\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Rak200\HttpInput\Exception\InputException;
use Rak200\HttpInput\Exception\InvalidInputException;
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

    public function testKeyIsNullUntilATerminalBindsIt(): void
    {
        $this->assertNull(new InvalidInputException('must be an integer')->key());
    }

    public function testForKeyBindsTheKeyAndReturnsTheFailure(): void
    {
        $failure = new InvalidInputException('must be an integer');

        $this->assertSame($failure, $failure->forKey('page'));   // fluent, so a throw site can bind inline
        $this->assertSame('page', $failure->key());
    }

    public function testKeyUnderReturnsTheParentForAFieldLevelFailure(): void
    {
        $this->assertSame('page', new InvalidInputException('must be an integer')->keyUnder('page'));
    }

    public function testKeyUnderComposesTheParentWithARelativePath(): void
    {
        $failure = new InvalidInputException('must be an integer')->nest('0');

        $this->assertSame('tags.0', $failure->keyUnder('tags'));
    }
}
