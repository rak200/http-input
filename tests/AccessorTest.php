<?php

declare(strict_types=1);

namespace Rak200\HttpInput\Tests;

use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;
use Rak200\HttpInput\Accessor;
use Rak200\HttpInput\Constraint;
use Rak200\HttpInput\Exception\InvalidInputException;
use Rak200\HttpInput\Exception\LengthInputException;
use Rak200\HttpInput\Exception\MissingInputException;
use Rak200\HttpInput\Exception\OutOfRangeInputException;
use Rak200\HttpInput\Input;
use Rak200\HttpInput\Rule;
use Rak200\HttpInput\Tests\Fixture\Priority;
use Rak200\HttpInput\Violation;

use function is_int;

/**
 * @internal
 *
 * @coversNothing
 */
final class AccessorTest extends TestCase
{
    // --- value(): the strict terminal -----------------------------------

    public function testValueReturnsTheCoercedValue(): void
    {
        $this->assertSame(3, Input::from(['page' => '3'], 'page')->int()->min(1)->value());
        $this->assertSame('hello', Input::from(['q' => 'hello'], 'q')->str()->value());
        $this->assertSame(9.99, Input::from(['price' => '9.99'], 'price')->float()->value());
        $this->assertSame(7, Input::from(['qty' => '7'], 'qty')->num()->value());
        $this->assertSame(0.5, Input::from(['ratio' => '0.5'], 'ratio')->num()->value());   // num() preserves the presentation
        $this->assertTrue(Input::from(['remember' => 'on'], 'remember')->bool()->value());
    }

    public function testValueThrowsMissingOnAnAbsentKey(): void
    {
        $this->expectException(MissingInputException::class);
        $this->expectExceptionMessage('is required');

        Input::from([], 'page')->int()->value();
    }

    public function testValueThrowsInvalidOnAnUncoercibleValue(): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('must be an integer');

        Input::from(['page' => 'abc'], 'page')->int()->value();
    }

    public function testValueThrowsTheFirstFailureOnly(): void
    {
        try {
            Input::from(['slug' => 'a'], 'slug')->str()->minLen(3)->pattern('/\d/')->value();
            $this->fail('value() should have thrown');
        } catch (LengthInputException $exception) {
            $this->assertSame('must be at least 3 characters', $exception->getMessage());
        }
    }

    public function testValueThrowsOutOfRangeBelowTheBound(): void
    {
        $this->expectException(OutOfRangeInputException::class);

        Input::from(['page' => '0'], 'page')->int()->min(1)->value();
    }

    public function testValueThrowsOutOfRangeAboveTheBound(): void
    {
        $this->expectException(OutOfRangeInputException::class);

        Input::from(['page' => '11'], 'page')->int()->max(10)->value();
    }

    public function testAbsentBareBoolIsFalseEvenForValue(): void
    {
        $this->assertFalse(Input::from([], 'remember')->bool()->value());
    }

    public function testAbsentRequiredBoolThrowsMissing(): void
    {
        $this->expectException(MissingInputException::class);

        Input::from([], 'remember')->bool()->required()->value();
    }

    public function testPresentNullIsPresentNotMissing(): void
    {
        $this->expectException(InvalidInputException::class);   // fails the coercer, not presence

        Input::from(['q' => null], 'q')->str()->value();
    }

    // --- orNull() / orElse(): the lenient terminals ----------------------

    public function testOrNullReturnsTheValueWhenTheChainPasses(): void
    {
        $this->assertSame('hello', Input::from(['q' => 'hello'], 'q')->str()->orNull());
    }

    public function testOrNullReturnsNullOnAbsenceOrFailure(): void
    {
        $this->assertNull(Input::from([], 'q')->str()->orNull());
        $this->assertNull(Input::from(['q' => 'abc'], 'q')->int()->orNull());
        $this->assertNull(Input::from([], 'q')->str()->required()->orNull());   // Missing is discarded too
    }

    public function testOrNullStillYieldsTheLegitimateFalseForAnAbsentBool(): void
    {
        $this->assertFalse(Input::from([], 'remember')->bool()->orNull());
    }

    public function testOrElseFallsBackOnAbsenceOrFailure(): void
    {
        $this->assertSame(1, Input::from([], 'page')->int()->orElse(1));
        $this->assertSame(1, Input::from(['page' => 'abc'], 'page')->int()->orElse(1));
        $this->assertSame(1, Input::from(['page' => '0'], 'page')->int()->min(1)->orElse(1));
    }

    public function testOrElseReturnsTheValueWhenTheChainPasses(): void
    {
        $this->assertSame(3, Input::from(['page' => '3'], 'page')->int()->min(1)->orElse(1));
    }

    public function testOrElseIgnoresTheDefaultForAnAbsentBareBool(): void
    {
        // Absence is part of the bare bool vocabulary: a legitimate false,
        // not a failure to fall back from.
        $this->assertFalse(Input::from([], 'remember')->bool()->orElse(true));
    }

    // --- literal keys (roadmap item 1, absorbed here) --------------------

    public function testKeysAreLookedUpLiterallyNotAsDotPaths(): void
    {
        $this->assertSame(5, Input::from(['a.b' => '5'], 'a.b')->int()->value());
    }

    public function testANestedStructureIsNotResolvedByADottedKey(): void
    {
        $this->expectException(MissingInputException::class);

        Input::from(['a' => ['b' => '5']], 'a.b')->int()->value();
    }

    // --- chain grammar (programmer errors) -------------------------------

    public function testATerminalWithoutACoercerIsALogicException(): void
    {
        $this->expectException(LogicException::class);

        Input::from(['q' => 'x'], 'q')->value();
    }

    public function testAVerifierWithoutACoercerIsALogicException(): void
    {
        $this->expectException(LogicException::class);

        Input::from(['q' => 'x'], 'q')->min(1);
    }

    public function testASecondCoercerIsALogicException(): void
    {
        $this->expectException(LogicException::class);

        Input::from(['q' => 'x'], 'q')->int()->str();
    }

    public function testGetOutsideCollectModeIsALogicException(): void
    {
        $this->expectException(LogicException::class);

        Input::from(['q' => 'x'], 'q')->str()->get();   // from() carries no collector
    }

    // --- chain behaviour through the accessor ----------------------------

    public function testCoerceOptsIntoLenientCoercion(): void
    {
        $this->assertSame(42, Input::from(['n' => '42.0'], 'n')->int()->coerce()->value());
    }

    public function testRequiresGateSkipsCrossFieldVerifiersOnANullDependency(): void
    {
        $password = null;   // the dependency failed its own read

        $confirmation = Input::from(['pwc' => 'anything'], 'pwc')
            ->str()->required()->requires($password)->sameAs($password)
            ->orNull()
        ;

        $this->assertSame('anything', $confirmation);   // no spurious mismatch error
    }

    public function testCrossFieldSameAsRunsWhenTheDependencyIsPresent(): void
    {
        $password = 'secret';
        $source = ['pwc' => 'other'];

        $this->assertNull(
            Input::from($source, 'pwc')->str()->requires($password)->sameAs($password)->orNull(),
        );
    }

    public function testLenBetweenBoundsTheLengthInclusively(): void
    {
        $this->assertSame('abc', Input::from(['slug' => 'abc'], 'slug')->str()->lenBetween(1, 3)->value());

        $this->expectException(LengthInputException::class);
        $this->expectExceptionMessage('must be between 1 and 3 characters');

        Input::from(['slug' => 'abcd'], 'slug')->str()->lenBetween(1, 3)->value();
    }

    public function testUrlVerifiesTheFormat(): void
    {
        $link = 'https://example.com/x';

        $this->assertSame($link, Input::from(['link' => $link], 'link')->str()->url()->value());
        $this->assertNull(Input::from(['link' => 'not a url'], 'link')->str()->url()->orNull());
    }

    public function testInVerifiesMembership(): void
    {
        $sizes = ['s', 'm', 'l'];

        $this->assertSame('m', Input::from(['size' => 'm'], 'size')->str()->in($sizes)->value());
        $this->assertNull(Input::from(['size' => 'x'], 'size')->str()->in($sizes)->orNull());
    }

    public function testCustomSatisfySeesTheCoercedValue(): void
    {
        $value = Input::from(['n' => '9'], 'n')
            ->int()
            ->satisfy(static fn (mixed $v): bool => is_int($v) && $v % 3 === 0, 'must be divisible by 3')
            ->value()
        ;

        $this->assertSame(9, $value);
    }

    public function testCustomRuleConstraintSeesTheCoercedValue(): void
    {
        $even = new class implements Constraint {
            public function check(mixed $value): ?Violation
            {
                return is_int($value) && $value % 2 === 0 ? null : new Violation('must be even');
            }
        };

        $this->assertSame(8, Input::from(['n' => '8'], 'n')->int()->rule($even)->value());
        $this->assertNull(Input::from(['n' => '9'], 'n')->int()->rule($even)->orNull());
    }

    public function testDomainCoercersOpenTheChainThroughTheAccessor(): void
    {
        $born = Input::from(['born' => '1990-12-10'], 'born')->date()->value();
        $this->assertInstanceOf(DateTimeImmutable::class, $born);
        $this->assertSame('1990-12-10', $born->format('Y-m-d'));

        $this->assertSame(
            Priority::High,
            Input::from(['priority' => '3'], 'priority')->enum(Priority::class)->value(),
        );
        $this->assertSame(
            [1, 2],
            Input::from(['ids' => ['1', '2']], 'ids')->listOf(Rule::int())->value(),
        );
        $this->assertSame(
            1736899200,
            Input::from(['at' => '1736899200'], 'at')->timestamp()->value(),
        );

        $opens = Input::from(['opens' => '09:30:00'], 'opens')->time()->value();
        $this->assertInstanceOf(DateTimeImmutable::class, $opens);
        $this->assertSame('09:30:00', $opens->format('H:i:s'));

        $seen = Input::from(['seen' => '2026-01-15 09:30:00'], 'seen')->datetime()->value();
        $this->assertInstanceOf(DateTimeImmutable::class, $seen);
        $this->assertSame('2026-01-15 09:30:00', $seen->format('Y-m-d H:i:s'));
    }

    public function testNullableAcceptsAPresentNullThroughTheAccessor(): void
    {
        $this->assertNull(Input::from(['q' => null], 'q')->str()->nullable()->value());
    }

    public function testAccessorsAreImmutable(): void
    {
        $base = Input::from(['page' => '5'], 'page')->int();
        $bounded = $base->min(10);

        $this->assertNotSame($base, $bounded);
        $this->assertSame(5, $base->value());          // base unchanged by min()
        $this->assertNull($bounded->orNull());
    }

    public function testFromReturnsAnAccessor(): void
    {
        $this->assertInstanceOf(Accessor::class, Input::from([], 'k'));
    }
}
