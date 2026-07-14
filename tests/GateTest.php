<?php

declare(strict_types=1);

namespace Rak200\HttpInput\Tests;

use PHPUnit\Framework\TestCase;
use Rak200\HttpInput\Exception\InputException;
use Rak200\HttpInput\Exception\InvalidInputException;
use Rak200\HttpInput\Exception\OutOfRangeInputException;
use Rak200\HttpInput\Gate;

/**
 * @internal
 *
 * @coversNothing
 */
final class GateTest extends TestCase
{
    /**
     * @var list<array{0: string, 1: InputException}>
     */
    private array $recorded = [];

    public function testAnActiveGateRecordsAFailedAssertion(): void
    {
        $gate = new Gate($this->record(...), true);

        $gate->assert('total', false, 'must not exceed the limit');

        $this->assertCount(1, $this->recorded);
        $this->assertSame('total', $this->recorded[0][0]);
        $this->assertInstanceOf(InvalidInputException::class, $this->recorded[0][1]);
        $this->assertSame('must not exceed the limit', $this->recorded[0][1]->getMessage());
    }

    public function testAnActiveGateRecordsNothingWhenTheConditionHolds(): void
    {
        new Gate($this->record(...), true)->assert('total', true, 'never recorded');

        $this->assertSame([], $this->recorded);
    }

    public function testAnInactiveGateSkipsTheAssertionEntirely(): void
    {
        new Gate($this->record(...), false)->assert('total', false, 'never recorded');

        $this->assertSame([], $this->recorded);
    }

    public function testTheExceptionSubtypeIsHonoured(): void
    {
        new Gate($this->record(...), true)->assert('total', false, 'too high', OutOfRangeInputException::class);

        $this->assertInstanceOf(OutOfRangeInputException::class, $this->recorded[0][1]);
    }

    public function testAssertReturnsTheGateForChaining(): void
    {
        $gate = new Gate($this->record(...), true);

        $this->assertSame($gate, $gate->assert('a', true, 'x'));
    }

    private function record(string $field, InputException $failure): void
    {
        $this->recorded[] = [$field, $failure];
    }
}
