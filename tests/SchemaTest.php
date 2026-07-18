<?php

declare(strict_types=1);

namespace Rak200\HttpInput\Tests;

use LogicException;
use PHPUnit\Framework\TestCase;
use Rak200\HttpInput\Exception\InvalidInputException;
use Rak200\HttpInput\Exception\MissingInputException;
use Rak200\HttpInput\Rule;
use Rak200\HttpInput\Schema;

/**
 * @internal
 *
 * @coversNothing
 */
final class SchemaTest extends TestCase
{
    public function testTheRfcSchemaExamplePassesEndToEnd(): void
    {
        $result = self::rfcSchema()->validate([
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'age' => 36,
            'phone' => null,
            'address' => ['city' => 'London', 'country' => 'UK'],
            'tags' => ['pioneer', 'math'],
            'items' => [['sku' => 'A-1', 'qty' => 2], ['sku' => 'B-2', 'qty' => 1]],
        ]);

        $this->assertFalse($result->fails());
        $this->assertSame(
            [
                'name' => 'Ada',
                'email' => 'ada@example.com',
                'age' => 36,
                'phone' => null,
                'address' => ['city' => 'London', 'country' => 'UK'],
                'tags' => ['pioneer', 'math'],
                'items' => [['sku' => 'A-1', 'qty' => 2], ['sku' => 'B-2', 'qty' => 1]],
            ],
            $result->values(),
        );
    }

    public function testFailuresAreKeyedByTheOffendingNodesPath(): void
    {
        $result = self::rfcSchema()->validate([
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'address' => ['country' => 'UK'],                 // city missing
            'items' => [['sku' => 'A-1', 'qty' => 0]],        // qty below min
        ]);

        $this->assertTrue($result->fails());
        $this->assertSame(['must be at least 1'], $result->messages()['items.0.qty']);
        $this->assertSame(['is required'], $result->messages()['address.city']);
        $this->assertInstanceOf(MissingInputException::class, $result->errors()['address.city'][0]);
    }

    public function testFailuresCarryTheirPathAsKeyAndSurviveValid(): void
    {
        $result = self::rfcSchema()->validate([
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'address' => ['city' => 'London', 'country' => 'UK'],
            'items' => [['sku' => 'A-1', 'qty' => 0]],   // the only failure: qty below min
        ]);

        $this->assertSame('items.0.qty', $result->errors()['items.0.qty'][0]->key());

        try {
            $result->valid();
            $this->fail('valid() should have thrown');
        } catch (InvalidInputException $exception) {
            $this->assertSame('items.0.qty', $exception->key());   // the throw terminal carries it too
        }
    }

    public function testBareLeavesAssertTheDecodedType(): void
    {
        $schema = Schema::object(['qty' => Rule::int()]);

        $this->assertTrue($schema->validate(['qty' => '42'])->fails());     // a JSON string is not an int
        $this->assertTrue($schema->validate(['qty' => 42.0])->fails());     // nor is a float
        $this->assertFalse($schema->validate(['qty' => 42])->fails());

        $lenient = Schema::object(['qty' => Rule::int()->coerce()]);
        $this->assertSame(['qty' => 42], $lenient->validate(['qty' => '42'])->values());
    }

    public function testUnknownKeysAreRejectedByDefault(): void
    {
        $schema = Schema::object(['name' => Rule::str()]);

        $result = $schema->validate(['name' => 'Ada', 'hack' => true]);

        $this->assertSame(['hack' => ['is not allowed']], $result->messages());
        $this->assertInstanceOf(InvalidInputException::class, $result->errors()['hack'][0]);
        $this->assertSame(['name' => 'Ada'], $result->values());   // never copied into the clean tree
    }

    public function testUnknownKeysArePathKeyedInNestedObjects(): void
    {
        $schema = Schema::object(['address' => Schema::object(['city' => Rule::str()])]);

        $result = $schema->validate(['address' => ['city' => 'London', 'zip' => 'E1']]);

        $this->assertSame(['address.zip' => ['is not allowed']], $result->messages());
    }

    public function testAllowUnknownKeysOptsOutPerObject(): void
    {
        $schema = Schema::object(['name' => Rule::str()])->allowUnknownKeys();

        $result = $schema->validate(['name' => 'Ada', 'extra' => 1]);

        $this->assertFalse($result->fails());
        $this->assertSame(['name' => 'Ada'], $result->values());   // ignored, still not copied
    }

    public function testAllowUnknownKeysIsImmutable(): void
    {
        $strict = Schema::object(['name' => Rule::str()]);
        $lenient = $strict->allowUnknownKeys();

        $this->assertNotSame($strict, $lenient);
        $this->assertTrue($strict->validate(['name' => 'Ada', 'x' => 1])->fails());
        $this->assertFalse($lenient->validate(['name' => 'Ada', 'x' => 1])->fails());
    }

    public function testAllowUnknownKeysOnAListIsALogicException(): void
    {
        $this->expectException(LogicException::class);

        Schema::listOf(Rule::str())->allowUnknownKeys();
    }

    public function testAMalformedObjectNodeIsReportedAtItsPath(): void
    {
        $schema = Schema::object(['address' => Schema::object(['city' => Rule::str()])]);

        $result = $schema->validate(['address' => 'not an object']);

        $this->assertSame(['address' => ['must be an object']], $result->messages());
    }

    public function testAListWhereAnObjectIsExpectedIsMalformed(): void
    {
        $schema = Schema::object(['address' => Schema::object(['city' => Rule::str()])]);

        $result = $schema->validate(['address' => ['a list', 'not an object']]);

        $this->assertSame(['address' => ['must be an object']], $result->messages());
    }

    public function testAMalformedListNodeIsReportedAtItsPath(): void
    {
        $schema = Schema::object(['tags' => Schema::listOf(Rule::str())]);

        $result = $schema->validate(['tags' => ['a' => 'x']]);   // assoc, not a list

        $this->assertSame(['tags' => ['must be a list']], $result->messages());
    }

    public function testAMalformedRootIsKeyedByTheEmptyPath(): void
    {
        $result = Schema::object(['name' => Rule::str()])->validate('scalar');

        $this->assertSame(['' => ['must be an object']], $result->messages());
        $this->assertSame('', $result->errors()[''][0]->key());   // the empty path is its key
        $this->assertSame([], $result->values());
    }

    public function testAbsentOptionalKeysAreSkippedWithANullValue(): void
    {
        $result = Schema::object(['age' => Rule::int()])->validate([]);

        $this->assertFalse($result->fails());
        $this->assertSame(['age' => null], $result->values());
    }

    public function testAnAbsentNestedObjectIsSkippedAsAWhole(): void
    {
        $schema = Schema::object(['address' => Schema::object(['city' => Rule::str()->required()])]);

        $result = $schema->validate([]);

        $this->assertFalse($result->fails());   // never recursed: its required leaves do not fire
        $this->assertSame(['address' => null], $result->values());
    }

    public function testNullableAcceptsAnExplicitNullLeaf(): void
    {
        $schema = Schema::object(['phone' => Rule::str()->nullable()->minLen(8)]);

        $result = $schema->validate(['phone' => null]);

        $this->assertFalse($result->fails());   // null short-circuits: minLen never ran
        $this->assertSame(['phone' => null], $result->values());
    }

    public function testAnAbsentBareBoolFollowsJsonPresenceRulesNotTheCheckboxConvention(): void
    {
        $result = Schema::object(['active' => Rule::bool()])->validate([]);

        $this->assertFalse($result->fails());
        $this->assertSame(['active' => null], $result->values());   // skipped — not the HTML false

        $required = Schema::object(['active' => Rule::bool()->required()])->validate([]);
        $this->assertSame(['is required'], $required->messages()['active']);
    }

    public function testTheEmptyDecodeAmbiguityIsAccepted(): void
    {
        // {} and [] both decode to [] under $assoc = true (RFC 0014 corner).
        $this->assertFalse(Schema::listOf(Rule::str())->validate([])->fails());
        $this->assertFalse(Schema::object(['age' => Rule::int()])->validate([])->fails());
    }

    public function testNestedListOfObjectsComposesPathsDeeply(): void
    {
        $schema = Schema::object([
            'orders' => Schema::listOf(Schema::object([
                'lines' => Schema::listOf(Schema::object(['qty' => Rule::int()->min(1)])),
            ])),
        ]);

        $result = $schema->validate(['orders' => [['lines' => [['qty' => 1], ['qty' => 0]]]]]);

        $this->assertSame(['must be at least 1'], $result->messages()['orders.0.lines.1.qty']);
    }

    public function testOutcomeIsTheJsonBridgeWithRelativeUnboundFailures(): void
    {
        $outcome = Schema::object(['qty' => Rule::int()->min(1)])->outcome(['qty' => 0]);

        $this->assertSame(['qty' => 0], $outcome->value);
        $this->assertCount(1, $outcome->failures);
        $this->assertSame('qty', $outcome->failures[0]->at());     // relative path, like any chain failure
        $this->assertNull($outcome->failures[0]->key());           // no field key until a terminal binds one
    }

    public function testAJsonLeafReadsAnEmbeddedDocumentInsideATree(): void
    {
        $schema = Schema::object(['meta' => Rule::json(Schema::object(['v' => Rule::int()]))]);

        $result = $schema->validate(['meta' => '{"v": 1}']);

        $this->assertFalse($result->fails());
        $this->assertSame(['meta' => ['v' => 1]], $result->values());

        $failing = $schema->validate(['meta' => '{"v": "x"}']);
        $this->assertSame(['meta.v' => ['must be an integer']], $failing->messages());
    }

    private static function rfcSchema(): Schema
    {
        return Schema::object([
            'name' => Rule::str()->required()->minLen(1),
            'email' => Rule::str()->required()->email(),
            'age' => Rule::int()->between(0, 120),
            'phone' => Rule::str()->nullable()->minLen(8),
            'address' => Schema::object([
                'city' => Rule::str()->required(),
                'country' => Rule::str()->required(),
            ]),
            'tags' => Schema::listOf(Rule::str()->minLen(1)),
            'items' => Schema::listOf(Schema::object([
                'sku' => Rule::str()->required(),
                'qty' => Rule::int()->min(1),
            ])),
        ]);
    }
}
