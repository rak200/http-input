<?php

declare(strict_types=1);

namespace Rak200\HttpInput\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rak200\HttpInput\Exception\InvalidInputException;
use Rak200\HttpInput\Rule;
use Stringable;

/**
 * @internal
 *
 * @coversNothing
 */
final class RuleStrTest extends TestCase
{
    public function testBareAcceptsAnyString(): void
    {
        $this->assertSame('Ada', Rule::str()->apply('Ada')->value);
        $this->assertSame('', Rule::str()->apply('')->value);
        $this->assertSame('42', Rule::str()->apply('42')->value);
    }

    #[DataProvider('provideBareRejected')]
    public function testBareRejectsNonStrings(mixed $value): void
    {
        $outcome = Rule::str()->apply($value);

        $this->assertTrue($outcome->failed());
        $this->assertCount(1, $outcome->failures);
        $this->assertInstanceOf(InvalidInputException::class, $outcome->failures[0]);
        $this->assertSame('must be a string', $outcome->failures[0]->getMessage());
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function provideBareRejected(): iterable
    {
        yield 'int' => [42];

        yield 'float' => [9.5];

        yield 'bool' => [true];

        yield 'null' => [null];

        yield 'array' => [['a']];
    }

    public function testBareTypedAssertsTheDecodedTypeTheSameWay(): void
    {
        $this->assertSame('x', Rule::str()->apply('x', typed: true)->value);
        $this->assertTrue(Rule::str()->apply(42, typed: true)->failed());
    }

    #[DataProvider('provideCoerceAccepted')]
    public function testCoerceCastsScalars(mixed $value, string $expected): void
    {
        $outcome = Rule::str()->coerce()->apply($value);

        $this->assertFalse($outcome->failed());
        $this->assertSame($expected, $outcome->value);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function provideCoerceAccepted(): iterable
    {
        yield 'string passes through' => ['Ada', 'Ada'];

        yield 'int casts' => [42, '42'];

        yield 'float casts' => [42.5, '42.5'];

        yield 'true casts to "1" (native cast)' => [true, '1'];

        yield 'false casts to "" (native cast)' => [false, ''];
    }

    public function testCoerceCastsStringables(): void
    {
        $stringable = new class implements Stringable {
            public function __toString(): string
            {
                return 'slug';
            }
        };

        $this->assertSame('slug', Rule::str()->coerce()->apply($stringable)->value);
    }

    public function testCoerceStillRejectsArraysAndNull(): void
    {
        $this->assertTrue(Rule::str()->coerce()->apply(['a'])->failed());
        $this->assertTrue(Rule::str()->coerce()->apply(null)->failed());
    }
}
