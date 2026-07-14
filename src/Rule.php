<?php

declare(strict_types=1);

namespace Rak200\HttpInput;

use Closure;
use DateTimeInterface;
use Rak200\HttpInput\Exception\FormatInputException;
use Rak200\HttpInput\Exception\InputException;
use Rak200\HttpInput\Exception\InvalidInputException;
use Rak200\HttpInput\Exception\LengthInputException;
use Rak200\HttpInput\Exception\MembershipInputException;
use Rak200\HttpInput\Exception\MismatchInputException;
use Rak200\HttpInput\Exception\MissingInputException;
use Rak200\HttpInput\Exception\OutOfRangeInputException;
use Rak200\Utils\Arr;
use Rak200\Utils\Filter;
use Rak200\Utils\Num;
use Rak200\Utils\Regex;
use Rak200\Utils\Str;
use Rak200\Utils\Type;
use Rak200\Utils\Url;

use function filter_var;

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
 * A bare coercer **asserts**: the value must already present as the declared
 * type — by its text format on the flat request bag ($typed = false), by its
 * decoded PHP type in a JSON tree ($typed = true). {@see coerce()} opts into
 * leniency: any *other* representation that maps to the type without loss is
 * also accepted (one step past `Filter::to*` — the whole decimal `42.0`
 * narrows to int 42; the fraction `42.5` never does).
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

    /**
     * @var self::BOOL|self::FLOAT|self::INT|self::NUM|self::STR
     */
    private string $type;

    private bool $lenient = false;

    private bool $required = false;

    /**
     * Chain steps in declaration order. Each step sees the coerced value and
     * answers: null (pass), a Violation (fail), or false (a `requires()` gate
     * tripped — skip every remaining step).
     *
     * @var list<Closure(mixed): (null|false|Violation)>
     */
    private array $steps = [];

    /**
     * @param self::BOOL|self::FLOAT|self::INT|self::NUM|self::STR $type
     */
    private function __construct(string $type)
    {
        $this->type = $type;
    }

    /**
     * Opens a string chain: bare, the value must be a string; under
     * {@see coerce()}, int/float/bool and Stringable objects are cast via
     * {@see Filter::toStr()}.
     */
    public static function str(): self
    {
        return new self(self::STR);
    }

    /**
     * Opens an integer chain: bare, the value must present as an integer
     * (the text `42`, or a decoded int); under {@see coerce()}, any lossless
     * representation — the whole decimal `42.0` (text or float) and numeric
     * text — narrows to int. A fraction never does.
     */
    public static function int(): self
    {
        return new self(self::INT);
    }

    /**
     * Opens a float chain: bare, the value must present as a decimal (the
     * text `42.0`/`42.5`, or a decoded float — a decoded int is rejected, no
     * widening); under {@see coerce()}, any numeric representation converts.
     */
    public static function float(): self
    {
        return new self(self::FLOAT);
    }

    /**
     * Opens a numeric chain yielding the int|float union: bare, any value
     * that presents as a number, preserving whichever it presents as; under
     * {@see coerce()}, numeric text converts (int text → int, decimal text →
     * float) and native numbers pass unchanged.
     */
    public static function num(): self
    {
        return new self(self::NUM);
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
        return new self(self::BOOL);
    }

    /**
     * Opts into lenient coercion: besides the bare presentation, any other
     * representation that maps to the declared type without loss is accepted.
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
            "must have at least {$length} items",
            "must be at least {$length} characters",
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
            "must have at most {$length} items",
            "must be at most {$length} characters",
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
     * ad-hoc set; membership in an enum is the `enum` coercer's job. Raises
     * MembershipInputException.
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
            static fn (mixed $value): ?false => Arr::contains($values, null) ? false : null,
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
     * every failure produced (none thrown). The coercer runs first — on
     * failure it short-circuits with a single InvalidInputException and no
     * verifier runs. Then every verifier runs in declaration order; a tripped
     * {@see requires()} gate skips the remaining steps.
     *
     * $typed selects what a bare coercer asserts: false — the flat request
     * bag, where every value is text and the assertion reads the text format
     * (RFC 0013); true — a decoded JSON tree, where values arrive typed and
     * the assertion reads the PHP type (RFC 0014). Lenient coercion
     * ({@see coerce()}) behaves identically in both.
     */
    public function apply(mixed $value, bool $typed = false): Outcome
    {
        [$ok, $coerced] = $this->runCoercer($value, $typed);
        if (!$ok) {
            return new Outcome(null, [new InvalidInputException(self::coercionMessage($this->type))]);
        }

        $failures = [];
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
     * Runs the opening coercer: `[true, coercedValue]` or `[false, null]`.
     *
     * @return array{0: bool, 1: mixed}
     */
    private function runCoercer(mixed $value, bool $typed): array
    {
        return match ($this->type) {
            self::STR => $this->coerceToStr($value),
            self::INT => $this->coerceToInt($value, $typed),
            self::FLOAT => $this->coerceToFloat($value, $typed),
            self::NUM => $this->coerceToNum($value, $typed),
            self::BOOL => $this->coerceToBool($value, $typed),
        };
    }

    /**
     * @return array{0: bool, 1: mixed}
     */
    private function coerceToStr(mixed $value): array
    {
        if ($this->lenient) {
            $string = Filter::toStr($value);

            return $string !== null ? [true, $string] : [false, null];
        }

        return Str::is($value) ? [true, $value] : [false, null];
    }

    /**
     * @return array{0: bool, 1: mixed}
     */
    private function coerceToInt(mixed $value, bool $typed): array
    {
        if ($this->lenient) {
            $int = Filter::toInt($value);
            if ($int !== null) {
                return [true, $int];
            }
            // One step past Filter::toInt — a whole decimal text ("42.0")
            // still narrows losslessly; a fraction never does.
            if (Str::is($value)) {
                $float = Num::parseFloatOrNull(Str::trim($value));
                if ($float !== null && Num::isFinite($float) && Num::floor($float) === $float) {
                    return [true, (int) $float];
                }
            }

            return [false, null];
        }
        if ($typed) {
            return Num::isInt($value) ? [true, $value] : [false, null];
        }

        return Str::is($value) && ($int = Num::parseIntOrNull($value)) !== null ? [true, $int] : [false, null];
    }

    /**
     * @return array{0: bool, 1: mixed}
     */
    private function coerceToFloat(mixed $value, bool $typed): array
    {
        if ($this->lenient) {
            $float = Filter::toFloat($value);

            return $float !== null ? [true, $float] : [false, null];
        }
        if ($typed) {
            return Num::isFloat($value) ? [true, $value] : [false, null];
        }
        // Bare on text: the value must present as a decimal — numeric, but
        // not integer text (that presents as an int).
        if (Str::is($value) && Num::parseIntOrNull($value) === null) {
            $float = Num::parseFloatOrNull($value);
            if ($float !== null) {
                return [true, $float];
            }
        }

        return [false, null];
    }

    /**
     * @return array{0: bool, 1: mixed}
     */
    private function coerceToNum(mixed $value, bool $typed): array
    {
        if ($this->lenient) {
            if (Num::isInt($value) || Num::isFloat($value)) {
                return [true, $value];
            }
            if (Str::is($value)) {
                $text = Str::trim($value);
                $int = Num::parseIntOrNull($text);
                if ($int !== null) {
                    return [true, $int];
                }
                $float = Num::parseFloatOrNull($text);
                if ($float !== null) {
                    return [true, $float];
                }
            }

            return [false, null];
        }
        if ($typed) {
            return Num::isInt($value) || Num::isFloat($value) ? [true, $value] : [false, null];
        }
        if (Str::is($value)) {
            $int = Num::parseIntOrNull($value);
            if ($int !== null) {
                return [true, $int];
            }
            $float = Num::parseFloatOrNull($value);
            if ($float !== null) {
                return [true, $float];
            }
        }

        return [false, null];
    }

    /**
     * @return array{0: bool, 1: mixed}
     */
    private function coerceToBool(mixed $value, bool $typed): array
    {
        if ($this->lenient) {
            $bool = Filter::toBool($value);

            return $bool !== null ? [true, $bool] : [false, null];
        }
        if ($typed) {
            return Type::isBool($value) ? [true, $value] : [false, null];
        }

        // Bare on text: exactly the checkbox pair ("on") and the API pair
        // ("true"/"false") — the rest of Filter::toBool's vocabulary only
        // via coerce().
        return match ($value) {
            'on', 'true' => [true, true],
            'false' => [true, false],
            default => [false, null],
        };
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
     * Materialises a Violation into the concrete exception: the violation's
     * own subtype when it names one, InvalidInputException otherwise.
     */
    private static function exceptionFor(Violation $violation): InputException
    {
        $class = $violation->exceptionClass ?? InvalidInputException::class;

        return new $class($violation->message);
    }

    /**
     * The coercion-failure message for each chain type.
     *
     * @param self::BOOL|self::FLOAT|self::INT|self::NUM|self::STR $type
     */
    private static function coercionMessage(string $type): string
    {
        return match ($type) {
            self::STR => 'must be a string',
            self::INT => 'must be an integer',
            self::FLOAT => 'must be a decimal number',
            self::NUM => 'must be a number',
            self::BOOL => 'must be a boolean',
        };
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
     * Renders the allowed set for the `in` message: scalars joined by a
     * comma, anything else as a generic phrase.
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
}
