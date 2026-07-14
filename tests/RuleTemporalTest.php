<?php

declare(strict_types=1);

namespace Rak200\HttpInput\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Rak200\HttpInput\Exception\InvalidInputException;
use Rak200\HttpInput\Exception\OutOfRangeInputException;
use Rak200\HttpInput\Rule;

/**
 * @internal
 *
 * @coversNothing
 */
final class RuleTemporalTest extends TestCase
{
    public function testDateParsesItsMask(): void
    {
        $value = Rule::date('Y-m-d')->apply('2026-01-15')->value;

        $this->assertInstanceOf(DateTimeImmutable::class, $value);
        $this->assertSame('2026-01-15', $value->format('Y-m-d'));
    }

    public function testDateDefaultsToIsoDate(): void
    {
        $this->assertFalse(Rule::date()->apply('2026-01-15')->failed());
    }

    public function testDateRejectsTextOutsideTheMask(): void
    {
        $outcome = Rule::date('Y-m-d')->apply('15/01/2026');

        $this->assertTrue($outcome->failed());
        $this->assertInstanceOf(InvalidInputException::class, $outcome->failures[0]);
        $this->assertSame('must be a valid date (Y-m-d)', $outcome->failures[0]->getMessage());
    }

    public function testDateRejectsNonStringCarriers(): void
    {
        $this->assertTrue(Rule::date()->apply(20260115)->failed());
        $this->assertTrue(Rule::date()->apply(null)->failed());
        $this->assertTrue(Rule::date()->apply(['2026-01-15'])->failed());
    }

    public function testDateAlwaysCoercesFromItsStringCarrier(): void
    {
        // The bare/coerce() distinction is inert on masks — JSON has no date
        // type, so the string carrier is parsed in both modes.
        $this->assertFalse(Rule::date()->apply('2026-01-15', typed: true)->failed());
        $this->assertFalse(Rule::date()->coerce()->apply('2026-01-15')->failed());
    }

    public function testTimeParsesItsMask(): void
    {
        $value = Rule::time('H:i')->apply('09:30')->value;

        $this->assertInstanceOf(DateTimeImmutable::class, $value);
        $this->assertSame('09:30', $value->format('H:i'));

        $message = Rule::time()->apply('not-a-time')->failures[0]->getMessage();
        $this->assertSame('must be a valid time (H:i:s)', $message);
    }

    public function testDatetimeParsesItsMask(): void
    {
        $value = Rule::datetime()->apply('2026-01-15 09:30:00')->value;

        $this->assertInstanceOf(DateTimeImmutable::class, $value);
        $this->assertSame('2026-01-15 09:30:00', $value->format('Y-m-d H:i:s'));

        $message = Rule::datetime()->apply('2026-01-15')->failures[0]->getMessage();
        $this->assertSame('must be a valid date and time (Y-m-d H:i:s)', $message);
    }

    public function testTimestampAcceptsAnyLosslessIntegerRepresentation(): void
    {
        $this->assertSame(1736899200, Rule::timestamp()->apply('1736899200')->value);
        $this->assertSame(1736899200, Rule::timestamp()->apply(1736899200, typed: true)->value);
        $this->assertSame(0, Rule::timestamp()->apply('0')->value);
    }

    public function testTimestampRejectsNonIntegerCarriers(): void
    {
        $outcome = Rule::timestamp()->apply('yesterday');

        $this->assertTrue($outcome->failed());
        $this->assertSame('must be a Unix timestamp', $outcome->failures[0]->getMessage());
        $this->assertTrue(Rule::timestamp()->apply('1.5')->failed());
    }

    public function testTemporalValuesAreOrderedByTheRangeVerifiers(): void
    {
        $start = new DateTimeImmutable('2026-01-10 00:00:00');
        $rule = Rule::datetime()->min($start);

        $this->assertFalse($rule->apply('2026-01-15 12:00:00')->failed());

        $outcome = $rule->apply('2026-01-05 12:00:00');
        $this->assertInstanceOf(OutOfRangeInputException::class, $outcome->failures[0]);
        $this->assertSame(
            'must be at least ' . $start->format(DateTimeImmutable::ATOM),
            $outcome->failures[0]->getMessage(),
        );
    }
}
