<?php

declare(strict_types=1);

namespace Rak200\HttpInput\Tests;

use PHPUnit\Framework\TestCase;
use Rak200\HttpInput\Constraint;
use Rak200\HttpInput\Exception\FormatInputException;
use Rak200\HttpInput\Exception\InvalidInputException;
use Rak200\HttpInput\Exception\LengthInputException;
use Rak200\HttpInput\Exception\MissingInputException;
use Rak200\HttpInput\Exception\OutOfRangeInputException;
use Rak200\HttpInput\Rule;
use Rak200\HttpInput\Violation;

use function is_int;
use function is_string;

/**
 * @internal
 *
 * @coversNothing
 */
final class RuleChainTest extends TestCase
{
    public function testCoercionFailureShortCircuitsTheVerifiers(): void
    {
        $outcome = Rule::int()->min(1)->apply('abc');

        $this->assertCount(1, $outcome->failures);   // only the coercion failure; min() never ran
        $this->assertInstanceOf(InvalidInputException::class, $outcome->failures[0]);
        $this->assertNull($outcome->value);
    }

    public function testEveryVerifierFailureIsCollectedInDeclarationOrder(): void
    {
        $outcome = Rule::str()->minLen(6)->pattern('/\d/')->apply('abc');

        $this->assertCount(2, $outcome->failures);
        $this->assertInstanceOf(LengthInputException::class, $outcome->failures[0]);
        $this->assertInstanceOf(FormatInputException::class, $outcome->failures[1]);
        $this->assertSame('abc', $outcome->value);
    }

    public function testVerifiersSeeTheCoercedValueNotTheRawText(): void
    {
        $outcome = Rule::int()->satisfy(static fn (mixed $value): bool => is_int($value), 'not coerced')->apply('42');

        $this->assertFalse($outcome->failed());
        $this->assertSame(42, $outcome->value);
    }

    public function testRequiresGateSkipsTheRestOfTheChainOnANullDependency(): void
    {
        $outcome = Rule::str()->requires(null)->sameAs('secret')->apply('other');

        $this->assertFalse($outcome->failed());   // sameAs skipped: no spurious cross-field error
        $this->assertSame('other', $outcome->value);
    }

    public function testRequiresGateLetsTheChainRunWhenDependenciesArePresent(): void
    {
        $outcome = Rule::str()->requires('secret')->sameAs('secret')->apply('other');

        $this->assertTrue($outcome->failed());
    }

    public function testRequiresGateIsPositional(): void
    {
        $outcome = Rule::str()->minLen(10)->requires(null)->sameAs('x')->apply('short');

        $this->assertCount(1, $outcome->failures);   // minLen ran (before the gate), sameAs did not
        $this->assertInstanceOf(LengthInputException::class, $outcome->failures[0]);
    }

    public function testRuleAppendsAReusableConstraint(): void
    {
        $even = new class implements Constraint {
            public function check(mixed $value): ?Violation
            {
                return is_int($value) && $value % 2 === 0 ? null : new Violation('must be even');
            }
        };

        $this->assertFalse(Rule::int()->rule($even)->apply('4')->failed());

        $outcome = Rule::int()->rule($even)->apply('3');
        $this->assertInstanceOf(InvalidInputException::class, $outcome->failures[0]);
        $this->assertSame('must be even', $outcome->failures[0]->getMessage());
    }

    public function testConstraintViolationSubtypeIsHonoured(): void
    {
        $positive = new class implements Constraint {
            public function check(mixed $value): ?Violation
            {
                return is_int($value) && $value > 0 ? null : new Violation('must be positive', OutOfRangeInputException::class);
            }
        };

        $outcome = Rule::int()->rule($positive)->apply('-3');

        $this->assertInstanceOf(OutOfRangeInputException::class, $outcome->failures[0]);
    }

    public function testSatisfyWrapsAOneOffCheck(): void
    {
        $rule = Rule::int()->satisfy(static fn (mixed $value): bool => is_int($value) && $value % 3 === 0, 'must be divisible by 3');

        $this->assertFalse($rule->apply('9')->failed());

        $outcome = $rule->apply('8');
        $this->assertInstanceOf(InvalidInputException::class, $outcome->failures[0]);
        $this->assertSame('must be divisible by 3', $outcome->failures[0]->getMessage());
    }

    public function testSatisfyRaisesTheGivenExceptionSubtype(): void
    {
        $outcome = Rule::int()
            ->satisfy(static fn (): bool => false, 'never', OutOfRangeInputException::class)
            ->apply('1')
        ;

        $this->assertInstanceOf(OutOfRangeInputException::class, $outcome->failures[0]);
    }

    public function testChainMethodsReturnNewInstances(): void
    {
        $base = Rule::int();
        $bounded = $base->min(10);
        $lenient = $base->coerce();

        $this->assertNotSame($base, $bounded);
        $this->assertNotSame($base, $lenient);
        $this->assertFalse($base->apply('5')->failed());       // base unchanged by min()
        $this->assertTrue($bounded->apply('5')->failed());
        $this->assertTrue($base->apply('42.0')->failed());     // base unchanged by coerce()
        $this->assertFalse($lenient->apply('42.0')->failed());
    }

    public function testTheFlagsReturnNewInstancesToo(): void
    {
        $base = Rule::int();
        $required = $base->required();
        $nullable = $base->nullable();

        $this->assertNotSame($base, $required);
        $this->assertNotSame($base, $nullable);
        $this->assertNull($base->applyAbsent());                              // base unchanged by required()
        $this->assertNotNull($required->applyAbsent());
        $this->assertTrue($base->apply(null, typed: true)->failed());         // base unchanged by nullable()
        $this->assertFalse($nullable->apply(null, typed: true)->failed());
    }

    public function testARuleIsReusableAcrossValues(): void
    {
        $rule = Rule::str()->minLen(2);

        $this->assertFalse($rule->apply('ab')->failed());
        $this->assertTrue($rule->apply('a')->failed());
        $this->assertFalse($rule->apply('cd')->failed());   // no state leaks between applications
    }

    public function testAbsentKeyIsTheTerminalsDecisionForNonBoolChains(): void
    {
        $this->assertNull(Rule::str()->applyAbsent());
        $this->assertNull(Rule::int()->coerce()->applyAbsent());
    }

    public function testRequiredChainReportsMissingOnAbsence(): void
    {
        $outcome = Rule::str()->required()->applyAbsent();

        $this->assertNotNull($outcome);
        $this->assertCount(1, $outcome->failures);
        $this->assertInstanceOf(MissingInputException::class, $outcome->failures[0]);
        $this->assertSame('is required', $outcome->failures[0]->getMessage());
        $this->assertNull($outcome->value);
    }

    public function testRequiredIsSatisfiedByAnyPresentValue(): void
    {
        $outcome = Rule::str()->required()->apply('');

        $this->assertFalse($outcome->failed());   // present-but-empty is present
        $this->assertSame('', $outcome->value);
    }

    public function testNullableShortCircuitsAnExplicitNullBeforeTheCoercer(): void
    {
        $outcome = Rule::int()->nullable()->min(1)->apply(null, typed: true);

        $this->assertFalse($outcome->failed());   // no coercion error, no verifier ran
        $this->assertNull($outcome->value);
    }

    public function testANullableChainStillCoercesNonNullValues(): void
    {
        $this->assertSame(5, Rule::int()->nullable()->apply(5, typed: true)->value);
        $this->assertTrue(Rule::int()->nullable()->apply('abc')->failed());
    }

    public function testRequiredAndNullableAreIndependent(): void
    {
        $rule = Rule::str()->required()->nullable();   // the key must exist, may be null

        $this->assertNull($rule->apply(null, typed: true)->value);   // present null → null

        $absent = $rule->applyAbsent();                              // absent → Missing
        $this->assertNotNull($absent);
        $this->assertInstanceOf(MissingInputException::class, $absent->failures[0]);
    }

    public function testSatisfyReceivesStringsUntouchedOnAStrChain(): void
    {
        $seen = null;
        Rule::str()->satisfy(static function (mixed $value) use (&$seen): bool {
            $seen = $value;

            return is_string($value);
        }, 'must be a string')->apply('raw');

        $this->assertSame('raw', $seen);
    }
}
