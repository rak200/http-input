<?php

declare(strict_types=1);

namespace Rak200\HttpInput\Tests;

use PHPUnit\Framework\TestCase;
use Rak200\HttpInput\Exception\FormatInputException;
use Rak200\HttpInput\Exception\LengthInputException;
use Rak200\HttpInput\Exception\MembershipInputException;
use Rak200\HttpInput\Exception\MismatchInputException;
use Rak200\HttpInput\Exception\OutOfRangeInputException;
use Rak200\HttpInput\Rule;

/**
 * @internal
 *
 * @coversNothing
 */
final class RuleVerifiersTest extends TestCase
{
    public function testMinAcceptsTheBoundItself(): void
    {
        $this->assertFalse(Rule::int()->min(3)->apply('3')->failed());
        $this->assertFalse(Rule::int()->min(3)->apply('4')->failed());
    }

    public function testMinRejectsBelowTheBound(): void
    {
        $outcome = Rule::int()->min(3)->apply('2');

        $this->assertTrue($outcome->failed());
        $this->assertInstanceOf(OutOfRangeInputException::class, $outcome->failures[0]);
        $this->assertSame('must be at least 3', $outcome->failures[0]->getMessage());
        $this->assertSame(2, $outcome->value);   // the coerced value survives a verifier failure
    }

    public function testMaxRejectsAboveTheBound(): void
    {
        $outcome = Rule::int()->max(10)->apply('11');

        $this->assertTrue($outcome->failed());
        $this->assertInstanceOf(OutOfRangeInputException::class, $outcome->failures[0]);
        $this->assertSame('must be at most 10', $outcome->failures[0]->getMessage());
        $this->assertFalse(Rule::int()->max(10)->apply('10')->failed());
    }

    public function testBetweenIsInclusiveOnBothEnds(): void
    {
        $rule = Rule::int()->between(1, 10);

        $this->assertFalse($rule->apply('1')->failed());
        $this->assertFalse($rule->apply('10')->failed());

        $outcome = $rule->apply('0');
        $this->assertInstanceOf(OutOfRangeInputException::class, $outcome->failures[0]);
        $this->assertSame('must be between 1 and 10', $outcome->failures[0]->getMessage());
    }

    public function testRangeBoundsRenderFloats(): void
    {
        // Temporal bounds (DateTimeInterface) are exercised with the
        // temporal coercers in milestone 5 — a chain can only produce an
        // ordered temporal value once date()/datetime() exist.
        $this->assertSame(
            'must be at least 1.5',
            Rule::float()->min(1.5)->apply('1.0')->failures[0]->getMessage(),
        );
    }

    public function testMinLenCountsCharactersNotBytes(): void
    {
        $rule = Rule::str()->minLen(4);

        $this->assertFalse($rule->apply('ação')->failed());   // 4 chars, 6 bytes

        $outcome = $rule->apply('abc');
        $this->assertTrue($outcome->failed());
        $this->assertInstanceOf(LengthInputException::class, $outcome->failures[0]);
        $this->assertSame('must be at least 4 characters', $outcome->failures[0]->getMessage());
    }

    public function testMaxLenRejectsLongerValues(): void
    {
        $outcome = Rule::str()->maxLen(3)->apply('abcd');

        $this->assertInstanceOf(LengthInputException::class, $outcome->failures[0]);
        $this->assertSame('must be at most 3 characters', $outcome->failures[0]->getMessage());
        $this->assertFalse(Rule::str()->maxLen(3)->apply('abc')->failed());
    }

    public function testLenBetweenIsInclusiveOnBothEnds(): void
    {
        $rule = Rule::str()->lenBetween(1, 3);

        $this->assertFalse($rule->apply('a')->failed());
        $this->assertFalse($rule->apply('abc')->failed());

        $outcome = $rule->apply('');
        $this->assertInstanceOf(LengthInputException::class, $outcome->failures[0]);
        $this->assertSame('must be between 1 and 3 characters', $outcome->failures[0]->getMessage());
    }

    public function testSameAsComparesStrictly(): void
    {
        $this->assertFalse(Rule::str()->sameAs('secret')->apply('secret')->failed());

        $outcome = Rule::str()->sameAs('secret')->apply('other');
        $this->assertInstanceOf(MismatchInputException::class, $outcome->failures[0]);
        $this->assertSame('must match', $outcome->failures[0]->getMessage());

        // strict: the int 1 does not match the text '1' read from another field
        $this->assertTrue(Rule::str()->sameAs(1)->apply('1')->failed());
    }

    public function testEmailAcceptsAValidAddress(): void
    {
        $this->assertFalse(Rule::str()->email()->apply('ada@example.com')->failed());

        $outcome = Rule::str()->email()->apply('not-an-email');
        $this->assertInstanceOf(FormatInputException::class, $outcome->failures[0]);
        $this->assertSame('must be a valid e-mail', $outcome->failures[0]->getMessage());
    }

    public function testUrlAcceptsAValidUrl(): void
    {
        $this->assertFalse(Rule::str()->url()->apply('https://example.com/x')->failed());

        $outcome = Rule::str()->url()->apply('not a url');
        $this->assertInstanceOf(FormatInputException::class, $outcome->failures[0]);
        $this->assertSame('must be a valid URL', $outcome->failures[0]->getMessage());
    }

    public function testPatternMatchesTheWholeSubjectRule(): void
    {
        $slug = Rule::str()->pattern('/^[a-z0-9-]+$/');

        $this->assertFalse($slug->apply('my-slug-1')->failed());

        $outcome = $slug->apply('My Slug!');
        $this->assertInstanceOf(FormatInputException::class, $outcome->failures[0]);
        $this->assertSame('must match the required format', $outcome->failures[0]->getMessage());
    }

    public function testInChecksMembershipStrictly(): void
    {
        $size = Rule::str()->in(['s', 'm', 'l']);

        $this->assertFalse($size->apply('m')->failed());

        $outcome = $size->apply('x');
        $this->assertInstanceOf(MembershipInputException::class, $outcome->failures[0]);
        $this->assertSame('must be one of s, m, l', $outcome->failures[0]->getMessage());

        // strict: the coerced text '1' is not the int 1
        $this->assertTrue(Rule::str()->in([1, 2])->apply('1')->failed());
    }

    public function testInRendersNonScalarSetsGenerically(): void
    {
        $outcome = Rule::str()->in([['nested']])->apply('x');

        $this->assertSame('must be one of the allowed values', $outcome->failures[0]->getMessage());
    }
}
