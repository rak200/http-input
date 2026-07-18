<?php

declare(strict_types=1);

namespace Rak200\HttpInput\Tests;

use PHPUnit\Framework\TestCase;
use Rak200\HttpInput\Exception\InputException;
use Rak200\HttpInput\Exception\InvalidInputException;
use Rak200\HttpInput\Exception\MismatchInputException;
use Rak200\HttpInput\Exception\MissingInputException;
use Rak200\HttpInput\Exception\OutOfRangeInputException;
use Rak200\HttpInput\Input;
use Rak200\HttpInput\Rule;
use Rak200\HttpInput\Schema;
use Rak200\HttpInput\Validator;

/**
 * @internal
 *
 * @coversNothing
 */
final class ValidatorTest extends TestCase
{
    public function testValidateReturnsAValidator(): void
    {
        $this->assertInstanceOf(Validator::class, Input::validate([]));
    }

    public function testAFreshValidatorHasNothingRecorded(): void
    {
        $form = Input::validate(['name' => 'Ada']);

        $this->assertFalse($form->fails());
        $this->assertSame([], $form->errors());
        $this->assertSame([], $form->messages());
        $this->assertSame([], $form->values());
    }

    public function testTheRfcFormExamplePassesEndToEnd(): void
    {
        $form = Input::validate([
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'age' => '36',
            'password' => 'correct-horse',
            'password_confirm' => 'correct-horse',
        ]);

        $name = $form->field('name')->str()->required()->minLen(2)->maxLen(80)->get();
        $email = $form->field('email')->str()->required()->email()->get();
        $age = $form->field('age')->int()->between(18, 120)->get();
        $pw = $form->field('password')->str()->required()->minLen(8)->get();
        $pwc = $form->field('password_confirm')->str()->required()->requires($pw)->sameAs($pw)->get();

        $this->assertFalse($form->fails());
        $this->assertSame('Ada Lovelace', $name);
        $this->assertSame('ada@example.com', $email);
        $this->assertSame(36, $age);
        $this->assertSame($pw, $pwc);
        $this->assertSame(
            [
                'name' => 'Ada Lovelace',
                'email' => 'ada@example.com',
                'age' => 36,
                'password' => 'correct-horse',
                'password_confirm' => 'correct-horse',
            ],
            $form->values(),
        );
    }

    public function testEveryFailureIsCollectedAcrossFields(): void
    {
        $form = Input::validate(['email' => 'nope', 'age' => '150']);

        $form->field('email')->str()->required()->email()->get();
        $form->field('age')->int()->between(18, 120)->get();
        $form->field('name')->str()->required()->get();

        $this->assertTrue($form->fails());
        $this->assertSame(
            [
                'email' => ['must be a valid e-mail'],
                'age' => ['must be between 18 and 120'],
                'name' => ['is required'],
            ],
            $form->messages(),
        );
    }

    public function testErrorsCarryTheTypedExceptionsPerField(): void
    {
        $form = Input::validate(['age' => '150']);
        $form->field('age')->int()->between(18, 120)->get();
        $form->field('name')->str()->required()->get();

        $errors = $form->errors();

        $this->assertInstanceOf(OutOfRangeInputException::class, $errors['age'][0]);
        $this->assertInstanceOf(MissingInputException::class, $errors['name'][0]);
        $this->assertContainsOnlyInstancesOf(InputException::class, $errors['age']);
    }

    public function testMultipleFailuresOnOneFieldAreAList(): void
    {
        $form = Input::validate(['slug' => 'a']);
        $form->field('slug')->str()->minLen(3)->pattern('/\d/')->get();

        $messages = $form->messages();

        $this->assertSame(['must be at least 3 characters', 'must match the required format'], $messages['slug']);
    }

    public function testGetReturnsTheBestEffortValueEvenWhenAVerifierFails(): void
    {
        $form = Input::validate(['age' => '150']);

        $age = $form->field('age')->int()->between(18, 120)->get();

        $this->assertSame(150, $age);                       // coerced fine; the range failed
        $this->assertSame(['age' => 150], $form->values());
        $this->assertTrue($form->fails());
    }

    public function testAnUncoercibleValueRecordsOneErrorAndYieldsNull(): void
    {
        $form = Input::validate(['age' => 'abc']);

        $age = $form->field('age')->int()->min(18)->get();

        $this->assertNull($age);
        $this->assertSame(['must be an integer'], $form->messages()['age']);   // short-circuit: min never ran
        $this->assertSame(['age' => null], $form->values());
    }

    public function testAnAbsentOptionalFieldIsSkippedWithANullValue(): void
    {
        $form = Input::validate([]);

        $age = $form->field('age')->int()->between(18, 120)->get();

        $this->assertNull($age);
        $this->assertFalse($form->fails());                 // skipped, not failed
        $this->assertSame(['age' => null], $form->values());
    }

    public function testAnAbsentRequiredFieldRecordsMissing(): void
    {
        $form = Input::validate([]);

        $form->field('name')->str()->required()->get();

        $this->assertTrue($form->fails());
        $this->assertInstanceOf(MissingInputException::class, $form->errors()['name'][0]);
        $this->assertSame(['name' => null], $form->values());
    }

    public function testAnAbsentBareBoolIsALegitimateFalseInTheValues(): void
    {
        $form = Input::validate([]);   // unchecked checkbox submits nothing

        $remember = $form->field('remember')->bool()->get();

        $this->assertFalse($remember);
        $this->assertFalse($form->fails());
        $this->assertSame(['remember' => false], $form->values());
    }

    public function testChainLevelRequiresPreventsSpuriousCrossFieldErrors(): void
    {
        $form = Input::validate(['password_confirm' => 'anything']);   // password absent

        $pw = $form->field('password')->str()->required()->minLen(8)->get();
        $form->field('password_confirm')->str()->required()->requires($pw)->sameAs($pw)->get();

        $this->assertTrue($form->fails());
        $this->assertArrayHasKey('password', $form->errors());          // its own failure
        $this->assertArrayNotHasKey('password_confirm', $form->errors());   // no spurious mismatch
    }

    public function testFormLevelAssertRecordsUnderTheGivenField(): void
    {
        $form = Input::validate(['password' => 'secret-one', 'password_confirm' => 'secret-two']);

        $pw = $form->field('password')->str()->required()->get();
        $pwc = $form->field('password_confirm')->str()->required()->get();
        $form->requires($pw, $pwc)->assert('password_confirm', $pw === $pwc, 'passwords must match');

        $this->assertTrue($form->fails());
        $this->assertSame(['passwords must match'], $form->messages()['password_confirm']);
        $this->assertInstanceOf(InvalidInputException::class, $form->errors()['password_confirm'][0]);
    }

    public function testFormLevelAssertIsGatedOnNullDependencies(): void
    {
        $form = Input::validate([]);   // both fields absent

        $pw = $form->field('password')->str()->orNull();
        $pwc = $form->field('password_confirm')->str()->orNull();
        $form->requires($pw, $pwc)->assert('password_confirm', $pw === $pwc, 'passwords must match');

        $this->assertArrayNotHasKey('password_confirm', $form->errors());   // gate inactive: no spurious error
    }

    public function testFormLevelAssertHonoursACustomExceptionSubtype(): void
    {
        $form = Input::validate([]);

        $form->requires()->assert('total', false, 'must not exceed the cart limit', OutOfRangeInputException::class);

        $this->assertInstanceOf(OutOfRangeInputException::class, $form->errors()['total'][0]);
    }

    public function testAssertionsChainOverOneSetOfDependencies(): void
    {
        $form = Input::validate([]);

        $form->requires('dep')
            ->assert('a', false, 'first')
            ->assert('b', false, 'second')
        ;

        $this->assertSame(['a' => ['first'], 'b' => ['second']], $form->messages());
    }

    public function testListElementFailuresAreIndexKeyedInTheBag(): void
    {
        $form = Input::validate(['tags' => ['s', 'x', 'm']]);

        $tags = $form->field('tags')->listOf(Rule::str()->in(['s', 'm', 'l']))->required()->minLen(1)->get();

        $this->assertTrue($form->fails());
        $this->assertSame(['tags.1' => ['must be one of s, m, l']], $form->messages());
        $this->assertSame(['s', 'x', 'm'], $tags);
        $this->assertSame(['tags' => ['s', 'x', 'm']], $form->values());
    }

    public function testAJsonFieldJoinsTheCollectFlow(): void
    {
        $form = Input::validate([
            'name' => 'Ada',
            'payload' => '{"items": [{"qty": 1}, {"qty": 0}], "note": 7}',
        ]);

        $name = $form->field('name')->str()->required()->get();
        $payload = $form->field('payload')->json(Schema::object([
            'items' => Schema::listOf(Schema::object(['qty' => Rule::int()->min(1)])),
            'note' => Rule::str(),
        ]))->required()->get();

        $this->assertTrue($form->fails());
        $this->assertSame(
            [
                'payload.items.1.qty' => ['must be at least 1'],
                'payload.note' => ['must be a string'],
            ],
            $form->messages(),
        );
        $this->assertSame('payload.items.1.qty', $form->errors()['payload.items.1.qty'][0]->key());
        $this->assertSame(['items' => [['qty' => 1], ['qty' => 0]], 'note' => null], $payload);
        $this->assertSame('Ada', $name);
        $this->assertSame($payload, $form->values()['payload']);   // best-effort tree survives
    }

    public function testAMalformedJsonFieldRecordsOneErrorUnderItsOwnKey(): void
    {
        $form = Input::validate(['payload' => '{oops']);

        $payload = $form->field('payload')->json(Schema::object(['a' => Rule::int()]))->get();

        $this->assertNull($payload);
        $this->assertSame(['payload' => ['must be valid JSON']], $form->messages());
        $this->assertSame(['payload' => null], $form->values());
    }

    public function testACleanJsonFieldLandsTypedInTheValues(): void
    {
        $form = Input::validate(['payload' => '{"qty": 2}']);

        $form->field('payload')->json(Schema::object(['qty' => Rule::int()->min(1)]))->get();

        $this->assertFalse($form->fails());
        $this->assertSame(['payload' => ['qty' => 2]], $form->values());
    }

    public function testAnAbsentRequiredCheckboxArrayIsMissingNotEmpty(): void
    {
        $form = Input::validate([]);   // no checkbox ticked: the array is absent, not []

        $form->field('tags')->listOf(Rule::str())->required()->minLen(1)->get();

        $this->assertInstanceOf(MissingInputException::class, $form->errors()['tags'][0]);
    }

    public function testFieldAndChainErrorsAccumulateOnTheSameField(): void
    {
        $form = Input::validate(['pwc' => 'x']);

        $pwc = $form->field('pwc')->str()->sameAs('secret')->get();
        $form->requires($pwc)->assert('pwc', false, 'and the form-level rule too');

        $this->assertInstanceOf(MismatchInputException::class, $form->errors()['pwc'][0]);
        $this->assertSame(['must match', 'and the form-level rule too'], $form->messages()['pwc']);
    }

    public function testRecordedFailuresCarryTheirBagKey(): void
    {
        $form = Input::validate(['age' => '150', 'tags' => ['s', 'x']]);

        $form->field('age')->int()->between(18, 120)->get();
        $form->field('tags')->listOf(Rule::str()->in(['s', 'm', 'l']))->get();

        $this->assertSame('age', $form->errors()['age'][0]->key());
        $this->assertSame('tags.1', $form->errors()['tags.1'][0]->key());   // composite, == the bag key
    }

    public function testAFormLevelAssertBindsTheFieldKey(): void
    {
        $form = Input::validate([]);

        $form->requires()->assert('total', false, 'must not exceed the cart limit');

        $this->assertSame('total', $form->errors()['total'][0]->key());
    }
}
