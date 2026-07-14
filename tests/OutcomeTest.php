<?php

declare(strict_types=1);

namespace Rak200\HttpInput\Tests;

use PHPUnit\Framework\TestCase;
use Rak200\HttpInput\Exception\InvalidInputException;
use Rak200\HttpInput\Outcome;

/**
 * @internal
 *
 * @coversNothing
 */
final class OutcomeTest extends TestCase
{
    public function testASuccessfulOutcomeCarriesTheValueAndNoFailures(): void
    {
        $outcome = new Outcome(42);

        $this->assertSame(42, $outcome->value);
        $this->assertSame([], $outcome->failures);
        $this->assertFalse($outcome->failed());
    }

    public function testAFailedOutcomeExposesItsFailures(): void
    {
        $failure = new InvalidInputException('must be an integer');
        $outcome = new Outcome(null, [$failure]);

        $this->assertNull($outcome->value);
        $this->assertSame([$failure], $outcome->failures);
        $this->assertTrue($outcome->failed());
    }
}
