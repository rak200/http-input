<?php

declare(strict_types=1);

namespace Rak200\HttpInput;

use BackedEnum;
use Closure;
use DateTimeInterface;
use LogicException;
use Rak200\HttpInput\Exception\FormatInputException;
use Rak200\HttpInput\Exception\InputException;
use Rak200\HttpInput\Exception\InvalidInputException;
use Rak200\HttpInput\Exception\LengthInputException;
use Rak200\HttpInput\Exception\MembershipInputException;
use Rak200\HttpInput\Exception\MismatchInputException;
use Rak200\HttpInput\Exception\MissingInputException;
use Rak200\HttpInput\Exception\OutOfRangeInputException;
use Rak200\Utils\Arr;
use Rak200\Utils\Dt;
use Rak200\Utils\Enum;
use Rak200\Utils\Filter;
use Rak200\Utils\Num;
use Rak200\Utils\Regex;
use Rak200\Utils\Str;
use Rak200\Utils\Type;
use Rak200\Utils\Url;
use UnitEnum;

use function filter_var;
use function is_subclass_of;

/**
 * A free-standing constraint chain: exactly one coercer, then verifiers —
 * with no source and no terminal (RFC 0013).
 *
 * Built fluently (`Rule::str()->minLen(2)->email()`) and used wherever a
 * chain is a *value*: the element rule in `listOf(Rule)` and the leaves of a
 * schema. A Rule is immutable — every chain method returns a new instance —
 * so one Rule can be reused across fields. Applying it to a value
 * ({@see apply()}) yields an {@see Outcome}: the coerced value plus every
 * failure the chain produced, none of them thrown; the terminal decides
 * their fate.
 *
 * A bare scalar coercer **asserts**: the value must already present as the
 * declared type — by its text format on the flat request bag
 * ($typed = false), by its decoded PHP type in a JSON tree ($typed = true).
 * {@see coerce()} opts into leniency: any *other* representation that maps
 * to the type without loss is also accepted (one step past `Filter::to*` —
 * the whole decimal `42.0` narrows to int 42; the fraction `42.5` never
 * does). The domain coercers ({@see date()}, {@see time()},
 * {@see datetime()}, {@see timestamp()}, {@see Enum()}) have no native
 * carrier, so they always coerce from their string or number carrier and
 * the flag is inert on them.
 *
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class Rule
{
    private const string STR = 'str';
    private const string INT = 'int';
    private const string FLOAT = 'float';
    private const string NUM = 'num';
    private const string BOOL = 'bool';
    private const string DATE = 'date';
    private const string TIME = 'time';
    private const string DATETIME = 'datetime';
    private const string TIMESTAMP = 'timestamp';
    private const string ENUM = 'enum';
    private const string LIST = 'list';

    /**
     * @var self::BOOL|self::DATE|self::DATETIME|self::ENUM|self::FLOAT|self::INT|self::LIST|self::NUM|self::STR|self::TIME|self::TIMESTAMP
     */
    private string $type;

    /**
     * The failure message when the opening coercer rejects the value.
     */
    private string $coercionMessage;

    private bool $lenient = false;

    private bool $required = false;

    private bool $nullable = false;

    /**
     * The mask of the temporal coercers (date/time/datetime).
     */
    private ?string $format = null;

    /**
     * @var null|class-string<UnitEnum>
     */
    private ?string $enumClass = null;

    private bool $enumByName = false;

    /**
     * The element rule of a listOf chain; null accepts mixed elements.
     */
    private ?self $element = null;

    /**
     * Chain steps in declaration order. Each step sees the coerced value and
     * answers: null (pass), a Violation (fail), or false (a `requires()` gate
     * tripped — skip every remaining step).
     *
     * @var list<Closure(mixed): (null|false|Violation)>
     */
    private array $steps = [];

    /**
     * @param self::BOOL|self::DATE|self::DATETIME|self::ENUM|self::FLOAT|self::INT|self::LIST|self::NUM|self::STR|self::TIME|self::TIMESTAMP $type
     */
    private function __construct(string $type, string $coercionMessage)
    {
        $this->type = $type;
        $this->coercionMessage = $coercionMessage;
    }

    /**
     * Opens a string chain: bare, the value must be a string; under
     * {@see coerce()}, int/float/bool and Stringable objects are cast via
     * {@see Filter::toStr()}.
     */
    public static function str(): self
    {
        return new self(self::STR, 'must be a string');
    }

    /**
     * Opens an integer chain: bare, the value must present as an integer
     * (the text `42`, or a decoded int); under {@see coerce()}, any lossless
     * representation — the whole decimal `42.0` (text or float) and numeric
     * text — narrows to int. A fraction never does.
     */
    public static function int(): self
    {
        return new self(self::INT, 'must be an integer');
    }

    /**
     * Opens a float chain: bare, the value must present as a decimal (the
     * text `42.0`/`42.5`, or a decoded float — a decoded int is rejected, no
     * widening); under {@see coerce()}, any numeric representation converts.
     */
    public static function float(): self
    {
        return new self(self::FLOAT, 'must be a decimal number');
    }

    /**
     * Opens a numeric chain yielding the int|float union: bare, any value
     * that presents as a number, preserving whichever it presents as; under
     * {@see coerce()}, numeric text converts (int text → int, decimal text →
     * float) and native numbers pass unchanged.
     */
    public static function num(): self
    {
        return new self(self::NUM, 'must be a number');
    }

    /**
     * Opens a boolean chain. Bare, only the two standard pairs are accepted:
     * the HTML checkbox pair (`on` → true, absent → false — see
     * {@see applyAbsent()}) and the API text pair (`true`/`false`); in a
     * typed tree, a native bool. Everything else `Filter::toBool` understands
     * (`1`/`0`, `yes`/`no`, `off`, the empty string, case variants) is
     * accepted only under {@see coerce()}.
     */
    public static function bool(): self
    {
        return new self(self::BOOL, 'must be a boolean');
    }

    /**
     * Opens a date chain: a string in $format, parsed via
     * {@see Dt::parseOrNull()} into a DateTimeImmutable. Always coerces from
     * its string carrier — JSON has no date type.
     */
    public static function date(string $format = 'Y-m-d'): self
    {
        $rule = new self(self::DATE, "must be a valid date ({$format})");
        $rule->format = $format;

        return $rule;
    }

    /**
     * Opens a time chain: a string in $format, parsed via
     * {@see Dt::parseOrNull()} into a DateTimeImmutable.
     */
    public static function time(string $format = 'H:i:s'): self
    {
        $rule = new self(self::TIME, "must be a valid time ({$format})");
        $rule->format = $format;

        return $rule;
    }

    /**
     * Opens a datetime chain: a string in $format, parsed via
     * {@see Dt::parseOrNull()} into a DateTimeImmutable.
     */
    public static function datetime(string $format = 'Y-m-d H:i:s'): self
    {
        $rule = new self(self::DATETIME, "must be a valid date and time ({$format})");
        $rule->format = $format;

        return $rule;
    }

    /**
     * Opens a Unix-timestamp chain: any lossless integer representation
     * (epoch seconds), yielding an int. Always coerces from its number
     * carrier.
     */
    public static function timestamp(): self
    {
        return new self(self::TIMESTAMP, 'must be a Unix timestamp');
    }

    /**
     * Opens an enum chain yielding the matching case. Matching defaults to
     * the backed **value** — the raw scalar is coerced to the backing type
     * first ({@see Enum} branching), so `'2'` matches an int-backed case —
     * and `$byName` opts into matching by case **name** instead
     * ({@see Enum::tryFromName()}), the only mode a pure enum supports.
     *
     * @param class-string<UnitEnum> $enumClass
     *
     * @throws LogicException when $enumClass has no cases, or is a pure enum
     *                        and $byName is false — programmer errors, not
     *                        input failures
     */
    public static function enum(string $enumClass, bool $byName = false): self
    {
        $cases = $enumClass::cases();
        if ($cases === []) {
            throw new LogicException("{$enumClass} has no cases — nothing can match.");
        }
        if (!$byName && !Enum::isBacked($cases[0])) {
            throw new LogicException("{$enumClass} is a pure enum — it matches by case name only; pass byName: true.");
        }
        $allowed = $byName ? Enum::names($enumClass) : Enum::values($enumClass);
        $rule = new self(self::ENUM, 'must be one of ' . self::allowed($allowed));
        $rule->enumClass = $enumClass;
        $rule->enumByName = $byName;

        return $rule;
    }

    /**
     * Opens a list chain (e.g. a checkbox array or multi-select): the value
     * must be a list, and each element must satisfy $element — a full Rule,
     * so elements coerce and verify like any other value; element failures
     * are index-keyed relative to the field ({@see InputException::at()},
     * `tags.0`). With no $element the elements pass through as mixed. Count
     * and presence are ordinary verifiers on top ({@see minLen()},
     * {@see required()}); an unchecked checkbox array is absent, not `[]`.
     */
    public static function listOf(?self $element = null): self
    {
        $rule = new self(self::LIST, 'must be a list');
        $rule->element = $element;

        return $rule;
    }

    /**
     * Opts into lenient coercion: besides the bare presentation, any other
     * representation that maps to the declared type without loss is accepted.
     * Inert on the domain coercers, which always coerce from their carrier.
     */
    public function coerce(): self
    {
        $rule = clone $this;
        $rule->lenient = true;

        return $rule;
    }

    /**
     * Requires the key to be present: an absent key becomes a
     * MissingInputException instead of being skipped (collect mode) or
     * yielding the bare-bool false.
     */
    public function required(): self
    {
        $rule = clone $this;
        $rule->required = true;

        return $rule;
    }

    /**
     * Accepts an explicit null *value*: on a nullable chain, null
     * short-circuits successfully before the coercer, yielding null — the
     * dual of {@see required()}, which rejects an *absent* key; the two are
     * independent (`required()->nullable()` = the key must exist, may be
     * null). Only a decoded JSON tree carries a real null; on the flat bag
     * the flag is inert.
     */
    public function nullable(): self
    {
        $rule = clone $this;
        $rule->nullable = true;

        return $rule;
    }

    /**
     * Verifies the value is at least $bound — any ordered value the coercer
     * produced (numbers, temporal values). Raises OutOfRangeInputException.
     */
    public function min(DateTimeInterface|float|int $bound): self
    {
        return $this->constraint(
            static fn (mixed $value): bool => $value >= $bound,
            'must be at least ' . self::bound($bound),
            OutOfRangeInputException::class,
        );
    }

    /**
     * Verifies the value is at most $bound — any ordered value the coercer
     * produced. Raises OutOfRangeInputException.
     */
    public function max(DateTimeInterface|float|int $bound): self
    {
        return $this->constraint(
            static fn (mixed $value): bool => $value <= $bound,
            'must be at most ' . self::bound($bound),
            OutOfRangeInputException::class,
        );
    }

    /**
     * Verifies the value lies in [$min, $max] (both inclusive). Raises
     * OutOfRangeInputException.
     */
    public function between(DateTimeInterface|float|int $min, DateTimeInterface|float|int $max): self
    {
        return $this->constraint(
            static fn (mixed $value): bool => $value >= $min && $value <= $max,
            'must be between ' . self::bound($min) . ' and ' . self::bound($max),
            OutOfRangeInputException::class,
        );
    }

    /**
     * Verifies the length is at least $length — characters for strings
     * (multibyte-safe), element count for arrays. Raises
     * LengthInputException.
     */
    public function minLen(int $length): self
    {
        return $this->lengthConstraint(
            static fn (int $actual): bool => $actual >= $length,
            "must have at least {$length} " . self::plural('item', $length),
            "must be at least {$length} " . self::plural('character', $length),
        );
    }

    /**
     * Verifies the length is at most $length — characters for strings
     * (multibyte-safe), element count for arrays. Raises
     * LengthInputException.
     */
    public function maxLen(int $length): self
    {
        return $this->lengthConstraint(
            static fn (int $actual): bool => $actual <= $length,
            "must have at most {$length} " . self::plural('item', $length),
            "must be at most {$length} " . self::plural('character', $length),
        );
    }

    /**
     * Verifies the length lies in [$min, $max] (both inclusive). Raises
     * LengthInputException.
     */
    public function lenBetween(int $min, int $max): self
    {
        return $this->lengthConstraint(
            static fn (int $actual): bool => $actual >= $min && $actual <= $max,
            "must have between {$min} and {$max} items",
            "must be between {$min} and {$max} characters",
        );
    }

    /**
     * Verifies the value strictly equals $other — the cross-field match
     * against an already-read value (`->requires($pw)->sameAs($pw)`). Raises
     * MismatchInputException.
     */
    public function sameAs(mixed $other): self
    {
        return $this->constraint(
            static fn (mixed $value): bool => $value === $other,
            'must match',
            MismatchInputException::class,
        );
    }

    /**
     * Verifies the value is a syntactically valid e-mail address. Raises
     * FormatInputException.
     */
    public function email(): self
    {
        return $this->constraint(
            static fn (mixed $value): bool => Str::is($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'must be a valid e-mail',
            FormatInputException::class,
        );
    }

    /**
     * Verifies the value is a valid URL (via {@see Url::is()}). Raises
     * FormatInputException.
     */
    public function url(): self
    {
        return $this->constraint(
            static fn (mixed $value): bool => Str::is($value) && Url::is($value),
            'must be a valid URL',
            FormatInputException::class,
        );
    }

    /**
     * Verifies the value matches the regular expression $pattern (via
     * {@see Regex::matches()}). Raises FormatInputException.
     */
    public function pattern(string $pattern): self
    {
        return $this->constraint(
            static fn (mixed $value): bool => Str::is($value) && Regex::matches($pattern, $value),
            'must match the required format',
            FormatInputException::class,
        );
    }

    /**
     * Verifies the value is a member of $allowed (strict comparison) — the
     * ad-hoc set; membership in an enum is the {@see Enum()} coercer's job.
     * Raises MembershipInputException.
     *
     * @param list<mixed> $allowed
     */
    public function in(array $allowed): self
    {
        return $this->constraint(
            static fn (mixed $value): bool => Arr::contains($allowed, $value),
            'must be one of ' . self::allowed($allowed),
            MembershipInputException::class,
        );
    }

    /**
     * Gates the rest of the chain on already-read dependencies: when any
     * $values entry is null, every step after this one is skipped, so no
     * verifier ever sees a null dependency and the field gains no spurious
     * cross-field error (the missing dependency reports its own).
     */
    public function requires(mixed ...$values): self
    {
        return $this->step(
            static fn (): ?false => Arr::contains($values, null) ? false : null,
        );
    }

    /**
     * Appends a reusable custom constraint (see {@see Constraint}).
     */
    public function rule(Constraint $constraint): self
    {
        return $this->step(
            static fn (mixed $value): ?Violation => $constraint->check($value),
        );
    }

    /**
     * Appends a one-off custom check: $check receives the coerced value and
     * returns true to pass. On failure the violation carries $message and,
     * when given, raises $exceptionClass instead of InvalidInputException.
     *
     * @param callable(mixed): bool             $check
     * @param null|class-string<InputException> $exceptionClass
     */
    public function satisfy(callable $check, string $message, ?string $exceptionClass = null): self
    {
        return $this->constraint($check(...), $message, $exceptionClass);
    }

    /**
     * Applies the chain to a present $value, yielding the coerced value plus
     * every failure produced (none thrown). On a {@see nullable()} chain an
     * explicit null short-circuits successfully first. The coercer runs
     * next — on failure it short-circuits with a single
     * InvalidInputException and no verifier runs; a listOf's element
     * failures do not block the list-level verifiers. Then every verifier
     * runs in declaration order; a tripped {@see requires()} gate skips the
     * remaining steps.
     *
     * $typed selects what a bare coercer asserts: false — the flat request
     * bag, where every value is text and the assertion reads the text format
     * (RFC 0013); true — a decoded JSON tree, where values arrive typed and
     * the assertion reads the PHP type (RFC 0014). Lenient coercion
     * ({@see coerce()}) behaves identically in both.
     */
    public function apply(mixed $value, bool $typed = false): Outcome
    {
        if ($this->nullable && $value === null) {
            return new Outcome(null);
        }

        [$ok, $coerced, $failures] = $this->runCoercer($value, $typed);
        if (!$ok) {
            return new Outcome(null, [new InvalidInputException($this->coercionMessage)]);
        }

        foreach ($this->steps as $step) {
            $result = $step($coerced);
            if ($result === false) {
                break;
            }
            if ($result instanceof Violation) {
                $failures[] = self::exceptionFor($result);
            }
        }

        return new Outcome($coerced, $failures);
    }

    /**
     * Answers for an absent key where the chain itself has an opinion:
     * a required() chain yields the MissingInputException failure, and a
     * bare bool chain yields false (an unchecked checkbox submits nothing,
     * so absence is a legitimate false — RFC 0013). Returns null when the
     * chain has no opinion and the terminal decides (value() raises Missing,
     * orNull()/orElse() fall back, collect mode skips the field).
     */
    public function applyAbsent(): ?Outcome
    {
        if ($this->required) {
            return new Outcome(null, [new MissingInputException('is required')]);
        }
        if ($this->type === self::BOOL) {
            return new Outcome(false);
        }

        return null;
    }

    /**
     * Appends a verifier built from a boolean check + failure metadata.
     *
     * @param callable(mixed): bool             $check
     * @param null|class-string<InputException> $exceptionClass
     */
    private function constraint(callable $check, string $message, ?string $exceptionClass = null): self
    {
        return $this->step(
            static fn (mixed $value): ?Violation => $check($value) ? null : new Violation($message, $exceptionClass),
        );
    }

    /**
     * Appends a raw chain step (verifier or gate), returning the new Rule.
     *
     * @param Closure(mixed): (null|false|Violation) $step
     */
    private function step(Closure $step): self
    {
        $rule = clone $this;
        $rule->steps[] = $step;

        return $rule;
    }

    /**
     * Runs the opening coercer: `[true, coercedValue, elementFailures]` or
     * `[false, null, []]`. Only a listOf produces element failures.
     *
     * @return array{0: bool, 1: mixed, 2: list<InputException>}
     */
    private function runCoercer(mixed $value, bool $typed): array
    {
        return match ($this->type) {
            self::STR => $this->coerceToStr($value),
            self::INT => $this->coerceToInt($value, $typed),
            self::FLOAT => $this->coerceToFloat($value, $typed),
            self::NUM => $this->coerceToNum($value, $typed),
            self::BOOL => $this->coerceToBool($value, $typed),
            self::DATE, self::TIME, self::DATETIME => $this->coerceToTemporal($value),
            self::TIMESTAMP => self::wrap(self::lenientInt($value)),
            self::ENUM => $this->coerceToEnum($value),
            self::LIST => $this->coerceToList($value, $typed),
        };
    }

    /**
     * @return array{0: bool, 1: mixed, 2: list<InputException>}
     */
    private function coerceToStr(mixed $value): array
    {
        if ($this->lenient) {
            return self::wrap(Filter::toStr($value));
        }

        return Str::is($value) ? [true, $value, []] : [false, null, []];
    }

    /**
     * @return array{0: bool, 1: mixed, 2: list<InputException>}
     */
    private function coerceToInt(mixed $value, bool $typed): array
    {
        if ($this->lenient) {
            return self::wrap(self::lenientInt($value));
        }
        if ($typed) {
            return Num::isInt($value) ? [true, $value, []] : [false, null, []];
        }

        return Str::is($value) && ($int = Num::parseIntOrNull($value)) !== null ? [true, $int, []] : [false, null, []];
    }

    /**
     * @return array{0: bool, 1: mixed, 2: list<InputException>}
     */
    private function coerceToFloat(mixed $value, bool $typed): array
    {
        if ($this->lenient) {
            return self::wrap(Filter::toFloat($value));
        }
        if ($typed) {
            return Num::isFloat($value) ? [true, $value, []] : [false, null, []];
        }
        // Bare on text: the value must present as a decimal — numeric, but
        // not integer text (that presents as an int).
        if (Str::is($value) && Num::parseIntOrNull($value) === null) {
            $float = Num::parseFloatOrNull($value);
            if ($float !== null) {
                return [true, $float, []];
            }
        }

        return [false, null, []];
    }

    /**
     * @return array{0: bool, 1: mixed, 2: list<InputException>}
     */
    private function coerceToNum(mixed $value, bool $typed): array
    {
        if ($this->lenient) {
            if (Num::isInt($value) || Num::isFloat($value)) {
                return [true, $value, []];
            }

            return Str::is($value) ? self::wrap(self::parseNum(Str::trim($value))) : [false, null, []];
        }
        if ($typed) {
            return Num::isInt($value) || Num::isFloat($value) ? [true, $value, []] : [false, null, []];
        }

        return Str::is($value) ? self::wrap(self::parseNum($value)) : [false, null, []];
    }

    /**
     * @return array{0: bool, 1: mixed, 2: list<InputException>}
     */
    private function coerceToBool(mixed $value, bool $typed): array
    {
        if ($this->lenient) {
            return self::wrap(Filter::toBool($value));
        }
        if ($typed) {
            return Type::isBool($value) ? [true, $value, []] : [false, null, []];
        }

        // Bare on text: exactly the checkbox pair ("on") and the API pair
        // ("true"/"false") — the rest of Filter::toBool's vocabulary only
        // via coerce().
        return match ($value) {
            'on', 'true' => [true, true, []],
            'false' => [true, false, []],
            default => [false, null, []],
        };
    }

    /**
     * The temporal masks parse their string carrier via Dt::parseOrNull.
     *
     * @return array{0: bool, 1: mixed, 2: list<InputException>}
     */
    private function coerceToTemporal(mixed $value): array
    {
        if (Str::is($value)) {
            return self::wrap(Dt::parseOrNull($value, $this->format));
        }

        return [false, null, []];
    }

    /**
     * Matches the raw scalar against the enum: by backed value (coercing to
     * the backing type first) or, opted in, by case name.
     *
     * @return array{0: bool, 1: mixed, 2: list<InputException>}
     */
    private function coerceToEnum(mixed $value): array
    {
        $class = $this->enumClass;
        if ($class === null) {
            return [false, null, []];   // defensive: unreachable for an enum-typed rule
        }
        if ($this->enumByName) {
            return self::wrap(Str::is($value) ? Enum::tryFromName($class, $value) : null);
        }
        $cases = $class::cases();
        if ($cases === []) {
            return [false, null, []];   // defensive: enum() rejects empty enums
        }
        // Branch on the backing type before narrowing the class-string, so
        // the scalar coerces to what tryFrom() expects.
        $intBacked = Enum::isBackedInt($cases[0]);
        if (!is_subclass_of($class, BackedEnum::class)) {
            return [false, null, []];   // defensive: enum() rejects pure enums without byName
        }
        $scalar = $intBacked ? Filter::toInt($value) : Filter::toStr($value);

        return self::wrap($scalar !== null ? $class::tryFrom($scalar) : null);
    }

    /**
     * Coerces a list element-by-element through the element rule; element
     * failures are index-keyed relative to the field and do not block the
     * list-level verifiers.
     *
     * @return array{0: bool, 1: mixed, 2: list<InputException>}
     */
    private function coerceToList(mixed $value, bool $typed): array
    {
        // Arr::is first: it carries the @phpstan-assert the analyzer needs
        // (Arr::isList narrows nothing), and the foreach below wants array.
        if (!Arr::is($value) || !Arr::isList($value)) {
            return [false, null, []];
        }
        if ($this->element === null) {
            return [true, $value, []];
        }
        $values = [];
        $failures = [];
        foreach ($value as $index => $item) {
            $outcome = $this->element->apply($item, $typed);
            $values[] = $outcome->value;
            foreach ($outcome->failures as $failure) {
                $failures[] = $failure->nest((string) $index);
            }
        }

        return [true, $values, $failures];
    }

    /**
     * Appends a length verifier — characters for strings (multibyte-safe),
     * element count for arrays; any other value fails.
     *
     * @param callable(int): bool $check
     */
    private function lengthConstraint(callable $check, string $itemsMessage, string $charactersMessage): self
    {
        return $this->step(
            static function (mixed $value) use ($check, $itemsMessage, $charactersMessage): ?Violation {
                if (Arr::is($value)) {
                    return $check(Arr::count($value)) ? null : new Violation($itemsMessage, LengthInputException::class);
                }
                if (Str::is($value) && $check(Str::len($value))) {
                    return null;
                }

                return new Violation($charactersMessage, LengthInputException::class);
            },
        );
    }

    /**
     * The lossless int coercion (`coerce()` semantics), shared by the int
     * and timestamp chains: Filter::toInt, plus one step past it — a whole
     * decimal text ("42.0") still narrows; a fraction never does.
     */
    private static function lenientInt(mixed $value): ?int
    {
        $int = Filter::toInt($value);
        if ($int !== null) {
            return $int;
        }
        if (Str::is($value)) {
            $float = Num::parseFloatOrNull(Str::trim($value));
            if ($float !== null && Num::isFinite($float) && Num::floor($float) === $float) {
                return (int) $float;
            }
        }

        return null;
    }

    /**
     * Parses numeric text preserving its presentation: integer text → int,
     * decimal text → float, anything else → null.
     */
    private static function parseNum(string $value): float|int|null
    {
        return Num::parseIntOrNull($value) ?? Num::parseFloatOrNull($value);
    }

    /**
     * Lifts a nullable coercion result into the coercer tuple: null means
     * the coercer rejected the value.
     *
     * @return array{0: bool, 1: mixed, 2: list<InputException>}
     */
    private static function wrap(mixed $coerced): array
    {
        return $coerced !== null ? [true, $coerced, []] : [false, null, []];
    }

    /**
     * Materialises a Violation into the concrete exception: the violation's
     * own subtype when it names one, InvalidInputException otherwise.
     */
    private static function exceptionFor(Violation $violation): InputException
    {
        $class = $violation->exceptionClass ?? InvalidInputException::class;

        return new $class($violation->message);
    }

    /**
     * Renders a range bound for a message: numbers as-is, temporal values in
     * ISO-8601.
     */
    private static function bound(DateTimeInterface|float|int $bound): string
    {
        return $bound instanceof DateTimeInterface ? $bound->format(DateTimeInterface::ATOM) : (string) $bound;
    }

    /**
     * Renders an allowed set for a message: scalars joined by a comma,
     * anything else as a generic phrase.
     *
     * @param list<mixed> $allowed
     */
    private static function allowed(array $allowed): string
    {
        $rendered = [];
        foreach ($allowed as $value) {
            if (!Str::is($value) && !Num::isInt($value) && !Num::isFloat($value)) {
                return 'the allowed values';
            }
            $rendered[] = (string) $value;
        }

        return Str::join($rendered, ', ');
    }

    /**
     * The unit word for a length message, singular when $count is 1.
     */
    private static function plural(string $unit, int $count): string
    {
        return $count === 1 ? $unit : $unit . 's';
    }
}
