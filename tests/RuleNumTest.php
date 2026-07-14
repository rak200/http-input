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
final class RuleNumTest extends TestCase
{
    #[DataProvider('provideBareTextAccepted')]
    public function testBareAcceptsNumericTextPreservingItsPresentation(string $value, float|int $expected): void
    {
        $outcome = Rule::num()->apply($value);

        $this->assertFalse($outcome->failed());
        $this->assertSame($expected, $outcome->value);   // assertSame checks the int-vs-float type too
    }

    /**
     * @return iterable<string, array{string, float|int}>
     */
    public static function provideBareTextAccepted(): iterable
    {
        yield 'integer text stays int' => ['42', 42];

        yield 'whole decimal text stays float' => ['42.0', 42.0];

        yield 'fraction text stays float' => ['42.5', 42.5];
    }

    #[DataProvider('provideBareTextRejected')]
    public function testBareRejectsNonNumericText(mixed $value): void
    {
        $outcome = Rule::num()->apply($value);

        $this->assertTrue($outcome->failed());
        $this->assertCount(1, $outcome->failures);
        $this->assertInstanceOf(InvalidInputException::class, $outcome->failures[0]);
        $this->assertSame('must be a number', $outcome->failures[0]->getMessage());
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function provideBareTextRejected(): iterable
    {
        yield 'non-numeric text' => ['abc'];

        yield 'empty text' => [''];

        yield 'native int (not text)' => [42];

        yield 'bool' => [true];
    }

    public function testBareTypedAcceptsEitherNativeNumberPreservingIt(): void
    {
        $this->assertSame(42, Rule::num()->apply(42, typed: true)->value);
        $this->assertSame(42.0, Rule::num()->apply(42.0, typed: true)->value);
        $this->assertSame(42.5, Rule::num()->apply(42.5, typed: true)->value);

        $this->assertTrue(Rule::num()->apply('42', typed: true)->failed());   // strings are not numbers
        $this->assertTrue(Rule::num()->apply(true, typed: true)->failed());
        $this->assertTrue(Rule::num()->apply(null, typed: true)->failed());
    }

    #[DataProvider('provideCoerceAccepted')]
    public function testCoerceAcceptsNumericRepresentationsPreservingTheKind(mixed $value, float|int $expected): void
    {
        $outcome = Rule::num()->coerce()->apply($value);

        $this->assertFalse($outcome->failed());
        $this->assertSame($expected, $outcome->value);
    }

    /**
     * @return iterable<string, array{mixed, float|int}>
     */
    public static function provideCoerceAccepted(): iterable
    {
        yield 'integer text becomes int' => ['42', 42];

        yield 'whole decimal text becomes float' => ['42.0', 42.0];

        yield 'fraction text becomes float' => ['42.5', 42.5];

        yield 'native int preserved' => [42, 42];

        yield 'native whole float preserved (not narrowed)' => [42.0, 42.0];

        yield 'native fraction preserved' => [42.5, 42.5];

        yield 'trimmed text' => [' 7 ', 7];
    }

    public function testCoerceRejectsNonNumericValues(): void
    {
        $this->assertTrue(Rule::num()->coerce()->apply('abc')->failed());
        $this->assertTrue(Rule::num()->coerce()->apply(true)->failed());
        $this->assertTrue(Rule::num()->coerce()->apply(null)->failed());
        $this->assertTrue(Rule::num()->coerce()->apply([])->failed());
    }
}
