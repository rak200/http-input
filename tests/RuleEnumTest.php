<?php

declare(strict_types=1);

namespace Rak200\HttpInput\Tests;

use LogicException;
use PHPUnit\Framework\TestCase;
use Rak200\HttpInput\Rule;
use Rak200\HttpInput\Tests\Fixture\Color;
use Rak200\HttpInput\Tests\Fixture\NoCases;
use Rak200\HttpInput\Tests\Fixture\Priority;
use Rak200\HttpInput\Tests\Fixture\Size;
use Rak200\HttpInput\Tests\Fixture\Solo;

/**
 * @internal
 *
 * @coversNothing
 */
final class RuleEnumTest extends TestCase
{
    public function testIntBackedEnumMatchesByBackedValueCoercingTheScalarFirst(): void
    {
        $this->assertSame(Priority::Mid, Rule::enum(Priority::class)->apply('2')->value);
        $this->assertSame(Priority::Mid, Rule::enum(Priority::class)->apply(2, typed: true)->value);
    }

    public function testStrBackedEnumMatchesByBackedValue(): void
    {
        $this->assertSame(Size::Medium, Rule::enum(Size::class)->apply('m')->value);
    }

    public function testAnUnknownBackedValueFailsWithTheAllowedSet(): void
    {
        $outcome = Rule::enum(Priority::class)->apply('9');

        $this->assertTrue($outcome->failed());
        $this->assertSame('must be one of 1, 2, 3', $outcome->failures[0]->getMessage());

        $this->assertSame(
            'must be one of s, m, l',
            Rule::enum(Size::class)->apply('x')->failures[0]->getMessage(),
        );
    }

    public function testAnUncoercibleScalarFails(): void
    {
        $this->assertTrue(Rule::enum(Priority::class)->apply('abc')->failed());
        $this->assertTrue(Rule::enum(Priority::class)->apply(['2'])->failed());
    }

    public function testByNameMatchesCaseNamesCaseSensitively(): void
    {
        $this->assertSame(Size::Medium, Rule::enum(Size::class, byName: true)->apply('Medium')->value);
        $this->assertSame(Color::Red, Rule::enum(Color::class, byName: true)->apply('Red')->value);

        $this->assertTrue(Rule::enum(Size::class, byName: true)->apply('medium')->failed());
        $this->assertTrue(Rule::enum(Size::class, byName: true)->apply('m')->failed());
    }

    public function testByNameMessageListsTheNames(): void
    {
        $this->assertSame(
            'must be one of Red, Green, Blue',
            Rule::enum(Color::class, byName: true)->apply('Purple')->failures[0]->getMessage(),
        );
    }

    public function testASingleCaseEnumMatchesItsOnlyCase(): void
    {
        $this->assertSame(Solo::Only, Rule::enum(Solo::class)->apply('1')->value);
    }

    public function testAPureEnumWithoutByNameIsALogicException(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('is a pure enum');

        Rule::enum(Color::class);
    }

    public function testAnEnumWithNoCasesIsALogicException(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('has no cases');

        Rule::enum(NoCases::class, byName: true);   // byName isolates the no-cases branch from the pure-enum one
    }
}
