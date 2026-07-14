<?php

declare(strict_types=1);

namespace Rak200\HttpInput\Tests;

use PHPUnit\Framework\TestCase;
use Rak200\HttpInput\Exception\InvalidInputException;
use Rak200\HttpInput\Exception\LengthInputException;
use Rak200\HttpInput\Exception\MembershipInputException;
use Rak200\HttpInput\Rule;

/**
 * @internal
 *
 * @coversNothing
 */
final class RuleListOfTest extends TestCase
{
    public function testCoercesEveryElementThroughTheElementRule(): void
    {
        $outcome = Rule::listOf(Rule::int())->apply(['1', '2', '3']);

        $this->assertFalse($outcome->failed());
        $this->assertSame([1, 2, 3], $outcome->value);
    }

    public function testAnEmptyListIsAValidList(): void
    {
        $outcome = Rule::listOf(Rule::str())->apply([]);

        $this->assertFalse($outcome->failed());
        $this->assertSame([], $outcome->value);
    }

    public function testWithoutAnElementRuleElementsPassThroughAsMixed(): void
    {
        $items = ['a', 1, null];

        $this->assertSame($items, Rule::listOf()->apply($items)->value);
    }

    public function testANonListIsMalformed(): void
    {
        foreach (['abc', 42, null, ['a' => 1]] as $value) {
            $outcome = Rule::listOf(Rule::str())->apply($value);

            $this->assertTrue($outcome->failed());
            $this->assertInstanceOf(InvalidInputException::class, $outcome->failures[0]);
            $this->assertSame('must be a list', $outcome->failures[0]->getMessage());
        }
    }

    public function testElementFailuresAreIndexKeyed(): void
    {
        $outcome = Rule::listOf(Rule::str()->in(['s', 'm', 'l']))->apply(['s', 'x', 'm']);

        $this->assertTrue($outcome->failed());
        $this->assertCount(1, $outcome->failures);
        $this->assertInstanceOf(MembershipInputException::class, $outcome->failures[0]);
        $this->assertSame('1', $outcome->failures[0]->at());
        $this->assertSame(['s', 'x', 'm'], $outcome->value);   // best-effort list survives
    }

    public function testAnUncoercibleElementContributesANullSlot(): void
    {
        $outcome = Rule::listOf(Rule::int())->apply(['1', 'x']);

        $this->assertSame([1, null], $outcome->value);
        $this->assertCount(1, $outcome->failures);
        $this->assertSame('1', $outcome->failures[0]->at());
        $this->assertSame('must be an integer', $outcome->failures[0]->getMessage());
    }

    public function testNestedListsComposeTheirPaths(): void
    {
        $outcome = Rule::listOf(Rule::listOf(Rule::int()))->apply([['1'], ['2', 'x']]);

        $this->assertCount(1, $outcome->failures);
        $this->assertSame('1.1', $outcome->failures[0]->at());
    }

    public function testTypedModePropagatesToTheElements(): void
    {
        $this->assertSame([1, 2], Rule::listOf(Rule::int())->apply([1, 2], typed: true)->value);
        $this->assertTrue(Rule::listOf(Rule::int())->apply(['1'], typed: true)->failed());
    }

    public function testElementFailuresDoNotBlockTheListLevelVerifiers(): void
    {
        $outcome = Rule::listOf(Rule::int())->minLen(3)->apply(['1', 'x']);

        $this->assertCount(2, $outcome->failures);                          // the element + the count
        $this->assertSame('1', $outcome->failures[0]->at());
        $this->assertInstanceOf(LengthInputException::class, $outcome->failures[1]);
        $this->assertSame('must have at least 3 items', $outcome->failures[1]->getMessage());
    }

    public function testLengthVerifiersCountElementsWithSingularGrammar(): void
    {
        $message = Rule::listOf(Rule::str())->minLen(1)->apply([])->failures[0]->getMessage();

        $this->assertSame('must have at least 1 item', $message);
        $this->assertFalse(Rule::listOf(Rule::str())->minLen(1)->apply(['a'])->failed());

        $this->assertSame(
            'must have at most 1 item',
            Rule::listOf(Rule::str())->maxLen(1)->apply(['a', 'b'])->failures[0]->getMessage(),
        );
    }

    public function testNullableElementsShortCircuitInsideTheList(): void
    {
        $outcome = Rule::listOf(Rule::int()->nullable())->apply([null, '2'], typed: true);

        $this->assertTrue($outcome->failed());   // '2' is a string in a typed tree
        $this->assertSame('1', $outcome->failures[0]->at());

        $this->assertSame([null, 2], Rule::listOf(Rule::int()->nullable())->apply([null, 2], typed: true)->value);
    }
}
