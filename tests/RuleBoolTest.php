<?php

declare(strict_types=1);

namespace Rak200\HttpInput\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rak200\HttpInput\Exception\InvalidInputException;
use Rak200\HttpInput\Exception\MissingInputException;
use Rak200\HttpInput\Rule;

/**
 * @internal
 *
 * @coversNothing
 */
final class RuleBoolTest extends TestCase
{
    public function testBareAcceptsExactlyTheCheckboxAndApiPairs(): void
    {
        $this->assertTrue(Rule::bool()->apply('on')->value);
        $this->assertTrue(Rule::bool()->apply('true')->value);
        $this->assertFalse(Rule::bool()->apply('false')->value);
    }

    #[DataProvider('provideBareTextRejected')]
    public function testBareRejectsTheRestOfTheVocabulary(mixed $value): void
    {
        $outcome = Rule::bool()->apply($value);

        $this->assertTrue($outcome->failed());
        $this->assertCount(1, $outcome->failures);
        $this->assertInstanceOf(InvalidInputException::class, $outcome->failures[0]);
        $this->assertSame('must be a boolean', $outcome->failures[0]->getMessage());
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function provideBareTextRejected(): iterable
    {
        yield 'off (coerce-only vocabulary)' => ['off'];

        yield 'digit one' => ['1'];

        yield 'digit zero' => ['0'];

        yield 'yes' => ['yes'];

        yield 'no' => ['no'];

        yield 'case variant of on' => ['ON'];

        yield 'case variant of true' => ['True'];

        yield 'empty text' => [''];

        yield 'native bool (not text)' => [true];

        yield 'native int' => [1];
    }

    public function testBareTypedAcceptsOnlyNativeBools(): void
    {
        $this->assertTrue(Rule::bool()->apply(true, typed: true)->value);
        $this->assertFalse(Rule::bool()->apply(false, typed: true)->value);

        $this->assertTrue(Rule::bool()->apply(1, typed: true)->failed());
        $this->assertTrue(Rule::bool()->apply('true', typed: true)->failed());
        $this->assertTrue(Rule::bool()->apply(null, typed: true)->failed());
    }

    #[DataProvider('provideCoerceAccepted')]
    public function testCoerceAcceptsTheFullFilterVocabulary(mixed $value, bool $expected): void
    {
        $outcome = Rule::bool()->coerce()->apply($value);

        $this->assertFalse($outcome->failed());
        $this->assertSame($expected, $outcome->value);
    }

    /**
     * @return iterable<string, array{mixed, bool}>
     */
    public static function provideCoerceAccepted(): iterable
    {
        yield 'digit one' => ['1', true];

        yield 'digit zero' => ['0', false];

        yield 'yes' => ['yes', true];

        yield 'no' => ['no', false];

        yield 'off' => ['off', false];

        yield 'on' => ['on', true];

        yield 'case variant' => ['ON', true];

        yield 'empty string' => ['', false];

        yield 'trimmed text' => [' true ', true];

        yield 'native int one' => [1, true];

        yield 'native int zero' => [0, false];

        yield 'native bool' => [true, true];
    }

    public function testCoerceRejectsValuesOutsideTheVocabulary(): void
    {
        $this->assertTrue(Rule::bool()->coerce()->apply('maybe')->failed());
        $this->assertTrue(Rule::bool()->coerce()->apply(2)->failed());
        $this->assertTrue(Rule::bool()->coerce()->apply(null)->failed());
    }

    public function testAbsentBareBoolIsALegitimateFalse(): void
    {
        $outcome = Rule::bool()->applyAbsent();

        $this->assertNotNull($outcome);
        $this->assertFalse($outcome->failed());
        $this->assertFalse($outcome->value);
    }

    public function testAbsentCoercingBoolIsStillFalse(): void
    {
        $outcome = Rule::bool()->coerce()->applyAbsent();

        $this->assertNotNull($outcome);
        $this->assertFalse($outcome->value);
    }

    public function testRequiredRestoresStrictPresenceForBools(): void
    {
        $outcome = Rule::bool()->required()->applyAbsent();

        $this->assertNotNull($outcome);
        $this->assertTrue($outcome->failed());
        $this->assertInstanceOf(MissingInputException::class, $outcome->failures[0]);
    }

    public function testTheCheckboxConventionIsHtmlsNotJsons(): void
    {
        // In a typed tree an absent bool follows the normal presence rules.
        $this->assertNull(Rule::bool()->applyAbsent(typed: true));

        $required = Rule::bool()->required()->applyAbsent(typed: true);
        $this->assertNotNull($required);
        $this->assertInstanceOf(MissingInputException::class, $required->failures[0]);
    }
}
