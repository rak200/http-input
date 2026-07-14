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
final class RuleFloatTest extends TestCase
{
    #[DataProvider('provideBareTextAccepted')]
    public function testBareAcceptsDecimalText(string $value, float $expected): void
    {
        $outcome = Rule::float()->apply($value);

        $this->assertFalse($outcome->failed());
        $this->assertSame($expected, $outcome->value);
    }

    /**
     * @return iterable<string, array{string, float}>
     */
    public static function provideBareTextAccepted(): iterable
    {
        yield 'whole decimal text' => ['42.0', 42.0];

        yield 'fraction text' => ['42.5', 42.5];

        yield 'negative fraction text' => ['-0.5', -0.5];
    }

    #[DataProvider('provideBareTextRejected')]
    public function testBareRejectsAnythingNotPresentingAsDecimalText(mixed $value): void
    {
        $outcome = Rule::float()->apply($value);

        $this->assertTrue($outcome->failed());
        $this->assertCount(1, $outcome->failures);
        $this->assertInstanceOf(InvalidInputException::class, $outcome->failures[0]);
        $this->assertSame('must be a decimal number', $outcome->failures[0]->getMessage());
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function provideBareTextRejected(): iterable
    {
        yield 'integer text (presents as int)' => ['42'];

        yield 'non-numeric text' => ['abc'];

        yield 'empty text' => [''];

        yield 'native float (not text)' => [42.0];

        yield 'array' => [['1.5']];
    }

    public function testBareTypedAcceptsOnlyNativeFloats(): void
    {
        $this->assertSame(42.0, Rule::float()->apply(42.0, typed: true)->value);
        $this->assertSame(42.5, Rule::float()->apply(42.5, typed: true)->value);

        $this->assertTrue(Rule::float()->apply(42, typed: true)->failed());      // no int widening
        $this->assertTrue(Rule::float()->apply('42.0', typed: true)->failed());  // strings are not numbers
        $this->assertTrue(Rule::float()->apply(null, typed: true)->failed());
    }

    #[DataProvider('provideCoerceAccepted')]
    public function testCoerceAcceptsAnyNumericRepresentation(mixed $value, float $expected): void
    {
        $outcome = Rule::float()->coerce()->apply($value);

        $this->assertFalse($outcome->failed());
        $this->assertSame($expected, $outcome->value);
    }

    /**
     * @return iterable<string, array{mixed, float}>
     */
    public static function provideCoerceAccepted(): iterable
    {
        yield 'integer text' => ['42', 42.0];

        yield 'whole decimal text' => ['42.0', 42.0];

        yield 'fraction text' => ['42.5', 42.5];

        yield 'native int' => [42, 42.0];

        yield 'native float' => [42.5, 42.5];

        yield 'trimmed text' => [' 9.5 ', 9.5];
    }

    #[DataProvider('provideCoerceRejected')]
    public function testCoerceRejectsNonNumericValues(mixed $value): void
    {
        $this->assertTrue(Rule::float()->coerce()->apply($value)->failed());
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function provideCoerceRejected(): iterable
    {
        yield 'non-numeric text' => ['abc'];

        yield 'bool' => [true];

        yield 'null' => [null];

        yield 'array' => [[1.5]];
    }
}
