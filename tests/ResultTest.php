<?php

declare(strict_types=1);

namespace Rak200\HttpInput\Tests;

use PHPUnit\Framework\TestCase;
use Rak200\HttpInput\Exception\MissingInputException;
use Rak200\HttpInput\Exception\OutOfRangeInputException;
use Rak200\HttpInput\Result;

/**
 * @internal
 *
 * @coversNothing
 */
final class ResultTest extends TestCase
{
    public function testACleanResultExposesTheTree(): void
    {
        $result = new Result([], ['name' => 'Ada']);

        $this->assertFalse($result->fails());
        $this->assertSame([], $result->errors());
        $this->assertSame([], $result->messages());
        $this->assertSame(['name' => 'Ada'], $result->values());
        $this->assertSame(['name' => 'Ada'], $result->valid());   // fail-fast terminal, nothing to throw
    }

    public function testAFailedResultExposesThePathKeyedBag(): void
    {
        $missing = new MissingInputException('is required');
        $range = new OutOfRangeInputException('must be at least 1');
        $result = new Result(
            ['address.city' => [$missing], 'items.0.qty' => [$range]],
            ['address' => ['city' => null]],
        );

        $this->assertTrue($result->fails());
        $this->assertSame(
            ['address.city' => ['is required'], 'items.0.qty' => ['must be at least 1']],
            $result->messages(),
        );
        $this->assertSame([$missing], $result->errors()['address.city']);
    }

    public function testValidThrowsTheFirstFailureInWalkOrder(): void
    {
        $first = new MissingInputException('is required');
        $result = new Result(
            ['address.city' => [$first], 'items.0.qty' => [new OutOfRangeInputException('must be at least 1')]],
            [],
        );

        try {
            $result->valid();
            $this->fail('valid() should have thrown');
        } catch (MissingInputException $exception) {
            $this->assertSame($first, $exception);
        }
    }
}
