<?php

declare(strict_types=1);

namespace Rak200\HttpInput\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rak200\HttpInput\Exception\InvalidInputException;
use Rak200\HttpInput\Rule;

/**
 * @internal
 *
 * @coversNothing
 */
final class RuleIntTest extends TestCase
{
    #[DataProvider('provideBareTextAccepted')]
    public function testBareAcceptsIntegerText(string $value, int $expected): void
    {
        $outcome = Rule::int()->apply($value);

        $this->assertFalse($outcome->failed());
        $this->assertSame($expected, $outcome->value);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function provideBareTextAccepted(): iterable
    {
        yield 'integer text' => ['42', 42];

        yield 'negative integer text' => ['-7', -7];

        yield 'zero text' => ['0', 0];
    }

    #[DataProvider('provideBareTextRejected')]
    public function testBareRejectsAnythingNotPresentingAsIntegerText(mixed $value): void
    {
        $outcome = Rule::int()->apply($value);

        $this->assertTrue($outcome->failed());
        $this->assertNull($outcome->value);
        $this->assertCount(1, $outcome->failures);
        $this->assertInstanceOf(InvalidInputException::class, $outcome->failures[0]);
        $this->assertSame('must be an integer', $outcome->failures[0]->getMessage());
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function provideBareTextRejected(): iterable
    {
        yield 'whole decimal text' => ['42.0'];

        yield 'fraction text' => ['42.5'];

        yield 'surrounding whitespace' => [' 42 '];

        yield 'non-numeric text' => ['abc'];

        yield 'empty text' => [''];

        yield 'native int (not text)' => [42];

        yield 'array' => [['4']];
    }

    public function testBareTypedAcceptsOnlyNativeInts(): void
    {
        $this->assertSame(42, Rule::int()->apply(42, typed: true)->value);
        $this->assertSame(-7, Rule::int()->apply(-7, typed: true)->value);

        $this->assertTrue(Rule::int()->apply(42.0, typed: true)->failed());     // no float, even whole
        $this->assertTrue(Rule::int()->apply('42', typed: true)->failed());     // strings are not numbers
        $this->assertTrue(Rule::int()->apply(true, typed: true)->failed());
        $this->assertTrue(Rule::int()->apply(null, typed: true)->failed());
    }

    #[DataProvider('provideCoerceAccepted')]
    public function testCoerceAcceptsAnyLosslessRepresentation(mixed $value, int $expected): void
    {
        $outcome = Rule::int()->coerce()->apply($value);

        $this->assertFalse($outcome->failed());
        $this->assertSame($expected, $outcome->value);
    }

    /**
     * @return iterable<string, array{mixed, int}>
     */
    public static function provideCoerceAccepted(): iterable
    {
        yield 'integer text' => ['42', 42];

        yield 'whole decimal text' => ['42.0', 42];

        yield 'trimmed text' => [' 42 ', 42];

        yield 'negative whole decimal text' => ['-8.0', -8];

        yield 'native int' => [42, 42];

        yield 'whole native float' => [42.0, 42];
    }

    #[DataProvider('provideCoerceRejected')]
    public function testCoerceNeverAcceptsALossyRepresentation(mixed $value): void
    {
        $this->assertTrue(Rule::int()->coerce()->apply($value)->failed());
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function provideCoerceRejected(): iterable
    {
        yield 'fraction text' => ['42.5'];

        yield 'native fraction' => [42.5];

        yield 'non-numeric text' => ['abc'];

        yield 'overflowing numeric text' => ['1e999'];   // parses to INF: whole, but not lossless

        yield 'bool' => [true];

        yield 'null' => [null];

        yield 'array' => [['4']];

        yield 'empty text' => [''];
    }

    public function testCoerceBehavesTheSameOverATypedTree(): void
    {
        $this->assertSame(42, Rule::int()->coerce()->apply('42.0', typed: true)->value);
        $this->assertSame(42, Rule::int()->coerce()->apply(42.0, typed: true)->value);
        $this->assertTrue(Rule::int()->coerce()->apply(42.5, typed: true)->failed());
    }
}
