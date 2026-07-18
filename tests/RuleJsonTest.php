<?php

declare(strict_types=1);

namespace Rak200\HttpInput\Tests;

use PHPUnit\Framework\TestCase;
use Rak200\HttpInput\Exception\InvalidInputException;
use Rak200\HttpInput\Exception\LengthInputException;
use Rak200\HttpInput\Exception\MissingInputException;
use Rak200\HttpInput\Rule;
use Rak200\HttpInput\Schema;

/**
 * @internal
 *
 * @coversNothing
 */
final class RuleJsonTest extends TestCase
{
    // --- bare json(): decode only ----------------------------------------

    public function testDecodesAnObjectDocument(): void
    {
        $outcome = Rule::json()->apply('{"name": "Ada", "age": 36}');

        $this->assertFalse($outcome->failed());
        $this->assertSame(['name' => 'Ada', 'age' => 36], $outcome->value);
    }

    public function testDecodesAListDocument(): void
    {
        $this->assertSame([1, 2, 3], Rule::json()->apply('[1, 2, 3]')->value);
    }

    public function testAnyValidRootPassesAsTheDecodedValue(): void
    {
        $this->assertSame(42, Rule::json()->apply('42')->value);
        $this->assertSame('x', Rule::json()->apply('"x"')->value);
        $this->assertTrue(Rule::json()->apply('true')->value);
    }

    public function testANullRootIsASuccessfulNull(): void
    {
        $outcome = Rule::json()->apply('null');

        $this->assertFalse($outcome->failed());
        $this->assertNull($outcome->value);
    }

    public function testAMalformedDocumentIsAnInputFailure(): void
    {
        foreach (['{oops', '', '{"a": }', "{'a': 1}"] as $document) {
            $outcome = Rule::json()->apply($document);

            $this->assertTrue($outcome->failed());
            $this->assertCount(1, $outcome->failures);
            $this->assertInstanceOf(InvalidInputException::class, $outcome->failures[0]);
            $this->assertSame('must be valid JSON', $outcome->failures[0]->getMessage());
            $this->assertNull($outcome->value);
        }
    }

    public function testANonStringValueIsRejected(): void
    {
        foreach ([42, 4.2, true, null, ['a' => 1]] as $value) {
            $outcome = Rule::json()->apply($value);

            $this->assertTrue($outcome->failed());
            $this->assertSame('must be valid JSON', $outcome->failures[0]->getMessage());
        }
    }

    // --- the string carrier: coerce() and typed are inert ----------------

    public function testTypedTreesReadTheSameStringCarrier(): void
    {
        $this->assertSame(['a' => 1], Rule::json()->apply('{"a": 1}', typed: true)->value);
        $this->assertTrue(Rule::json()->apply(['a' => 1], typed: true)->failed());   // already decoded ≠ a document
    }

    public function testCoerceIsInertOnTheDomainCoercer(): void
    {
        $this->assertSame(['a' => 1], Rule::json()->coerce()->apply('{"a": 1}')->value);
        $this->assertTrue(Rule::json()->coerce()->apply(42)->failed());   // still only the string carrier
    }

    // --- presence flags ---------------------------------------------------

    public function testNullableShortCircuitsOnAPresentNullValue(): void
    {
        $outcome = Rule::json()->nullable()->apply(null, typed: true);

        $this->assertFalse($outcome->failed());
        $this->assertNull($outcome->value);
    }

    public function testRequiredAnswersForTheAbsentKey(): void
    {
        $absent = Rule::json()->required()->applyAbsent();

        $this->assertNotNull($absent);
        $this->assertInstanceOf(MissingInputException::class, $absent->failures[0]);
    }

    public function testAnAbsentOptionalJsonHasNoOpinion(): void
    {
        $this->assertNull(Rule::json()->applyAbsent());
    }

    // --- json(Schema): the decoded tree runs through the schema ----------

    public function testASchemaYieldsTheCleanTypedTree(): void
    {
        $rule = Rule::json(Schema::object([
            'name' => Rule::str()->required(),
            'qty' => Rule::int()->min(1),
        ]));

        $outcome = $rule->apply('{"name": "Ada", "qty": 2}');

        $this->assertFalse($outcome->failed());
        $this->assertSame(['name' => 'Ada', 'qty' => 2], $outcome->value);
    }

    public function testSchemaFailuresCarryTheirPathRelativeToTheField(): void
    {
        $rule = Rule::json(Schema::object([
            'items' => Schema::listOf(Schema::object(['qty' => Rule::int()->min(1)])),
        ]));

        $outcome = $rule->apply('{"items": [{"qty": 1}, {"qty": 0}]}');

        $this->assertTrue($outcome->failed());
        $this->assertCount(1, $outcome->failures);
        $this->assertSame('items.1.qty', $outcome->failures[0]->at());
        $this->assertSame('must be at least 1', $outcome->failures[0]->getMessage());
    }

    public function testARootShapeFailureIsTheFieldsOwnWithANullValue(): void
    {
        $outcome = Rule::json(Schema::object(['a' => Rule::int()]))->apply('[1, 2]');

        $this->assertTrue($outcome->failed());
        $this->assertNull($outcome->failures[0]->at());   // the field's own failure
        $this->assertSame('must be an object', $outcome->failures[0]->getMessage());
        $this->assertNull($outcome->value);
    }

    public function testUnknownKeysAreRejectedThroughTheSchema(): void
    {
        $outcome = Rule::json(Schema::object(['a' => Rule::int()]))->apply('{"a": 1, "b": 2}');

        $this->assertCount(1, $outcome->failures);
        $this->assertSame('b', $outcome->failures[0]->at());
        $this->assertSame('is not allowed', $outcome->failures[0]->getMessage());
    }

    public function testSchemaLeavesAssertTheDecodedType(): void
    {
        $outcome = Rule::json(Schema::object(['qty' => Rule::int()]))->apply('{"qty": "2"}');

        $this->assertTrue($outcome->failed());   // a decoded string is not an int (RFC 0014)
        $this->assertSame('qty', $outcome->failures[0]->at());

        $lenient = Rule::json(Schema::object(['qty' => Rule::int()->coerce()]))->apply('{"qty": "2"}');
        $this->assertSame(['qty' => 2], $lenient->value);
    }

    public function testSchemaFailuresDoNotBlockTheLaterVerifiers(): void
    {
        $rule = Rule::json(Schema::object(['a' => Rule::int()]))->minLen(2);

        $outcome = $rule->apply('{"a": "x"}');

        $this->assertCount(2, $outcome->failures);                          // the leaf + the count
        $this->assertSame('a', $outcome->failures[0]->at());
        $this->assertInstanceOf(LengthInputException::class, $outcome->failures[1]);
        $this->assertSame('must have at least 2 items', $outcome->failures[1]->getMessage());
    }

    public function testAMalformedDocumentShortCircuitsBeforeTheSchema(): void
    {
        $outcome = Rule::json(Schema::object(['a' => Rule::int()]))->apply('{oops');

        $this->assertCount(1, $outcome->failures);
        $this->assertSame('must be valid JSON', $outcome->failures[0]->getMessage());
    }

    public function testVerifiersSeeTheDecodedTree(): void
    {
        $this->assertFalse(Rule::json()->minLen(2)->apply('[1, 2]')->failed());
        $this->assertTrue(Rule::json()->minLen(3)->apply('[1, 2]')->failed());
    }

    public function testRulesAreImmutableValues(): void
    {
        $base = Rule::json();
        $bounded = $base->minLen(3);

        $this->assertNotSame($base, $bounded);
        $this->assertFalse($base->apply('[1]')->failed());   // base unchanged by minLen()
        $this->assertTrue($bounded->apply('[1]')->failed());
    }
}
