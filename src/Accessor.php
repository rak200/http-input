<?php

declare(strict_types=1);

namespace Rak200\HttpInput;

use DateTimeInterface;
use LogicException;
use Rak200\HttpInput\Exception\InputException;
use Rak200\HttpInput\Exception\MissingInputException;
use Rak200\Utils\Arr;

/**
 * A {@see Rule} bound to a `(source, key)`, plus the terminals (RFC 0013).
 *
 * The accessor exposes the same chain API as Rule — exactly one coercer
 * opens the chain, then verifiers — and adds the terminals that decide a
 * failure's fate: {@see value()} throws the first, {@see orNull()} /
 * {@see orElse()} discard and fall back. Keys are looked up literally
 * (never as dot-paths), and the source is read with flat-request-bag
 * semantics: a bare coercer asserts the value's text format.
 *
 * Obtain one from {@see Input::from()} (or, in collect mode, from the
 * validator's `field()`); the accessor is immutable, so every chain call
 * returns a new instance. Reaching a terminal — or appending a verifier —
 * before a coercer has opened the chain is a programmer error
 * (LogicException), not an input failure: an implicit `str()` would
 * contradict "exactly one coercer opens the chain".
 *
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class Accessor
{
    /**
     * Binds $key (a literal key, never a dot-path) within $source. Prefer
     * the {@see Input::from()} facade; this constructor is its backing.
     *
     * @param array<array-key, mixed> $source
     */
    public function __construct(
        private readonly array $source,
        private readonly string $key,
        private readonly ?Rule $rule = null,
    ) {}

    /**
     * Opens the chain as a string read — see {@see Rule::str()}.
     */
    public function str(): self
    {
        return $this->open(Rule::str());
    }

    /**
     * Opens the chain as an integer read — see {@see Rule::int()}.
     */
    public function int(): self
    {
        return $this->open(Rule::int());
    }

    /**
     * Opens the chain as a float read — see {@see Rule::float()}.
     */
    public function float(): self
    {
        return $this->open(Rule::float());
    }

    /**
     * Opens the chain as an int|float read — see {@see Rule::num()}.
     */
    public function num(): self
    {
        return $this->open(Rule::num());
    }

    /**
     * Opens the chain as a boolean read — see {@see Rule::bool()}; on the
     * flat bag an absent bare bool is a legitimate false.
     */
    public function bool(): self
    {
        return $this->open(Rule::bool());
    }

    /**
     * Opts into lenient, lossless coercion — see {@see Rule::coerce()}.
     */
    public function coerce(): self
    {
        return $this->with($this->openedRule()->coerce());
    }

    /**
     * Requires the key to be present — see {@see Rule::required()}.
     */
    public function required(): self
    {
        return $this->with($this->openedRule()->required());
    }

    /**
     * Verifies the value is at least $bound — see {@see Rule::min()}.
     */
    public function min(DateTimeInterface|float|int $bound): self
    {
        return $this->with($this->openedRule()->min($bound));
    }

    /**
     * Verifies the value is at most $bound — see {@see Rule::max()}.
     */
    public function max(DateTimeInterface|float|int $bound): self
    {
        return $this->with($this->openedRule()->max($bound));
    }

    /**
     * Verifies the value lies in [$min, $max] — see {@see Rule::between()}.
     */
    public function between(DateTimeInterface|float|int $min, DateTimeInterface|float|int $max): self
    {
        return $this->with($this->openedRule()->between($min, $max));
    }

    /**
     * Verifies the length is at least $length — see {@see Rule::minLen()}.
     */
    public function minLen(int $length): self
    {
        return $this->with($this->openedRule()->minLen($length));
    }

    /**
     * Verifies the length is at most $length — see {@see Rule::maxLen()}.
     */
    public function maxLen(int $length): self
    {
        return $this->with($this->openedRule()->maxLen($length));
    }

    /**
     * Verifies the length lies in [$min, $max] — see {@see Rule::lenBetween()}.
     */
    public function lenBetween(int $min, int $max): self
    {
        return $this->with($this->openedRule()->lenBetween($min, $max));
    }

    /**
     * Verifies the value strictly equals an already-read value — see
     * {@see Rule::sameAs()}.
     */
    public function sameAs(mixed $other): self
    {
        return $this->with($this->openedRule()->sameAs($other));
    }

    /**
     * Verifies the value is a valid e-mail address — see {@see Rule::email()}.
     */
    public function email(): self
    {
        return $this->with($this->openedRule()->email());
    }

    /**
     * Verifies the value is a valid URL — see {@see Rule::url()}.
     */
    public function url(): self
    {
        return $this->with($this->openedRule()->url());
    }

    /**
     * Verifies the value matches $pattern — see {@see Rule::pattern()}.
     */
    public function pattern(string $pattern): self
    {
        return $this->with($this->openedRule()->pattern($pattern));
    }

    /**
     * Verifies membership in the ad-hoc set $allowed — see {@see Rule::in()}.
     *
     * @param list<mixed> $allowed
     */
    public function in(array $allowed): self
    {
        return $this->with($this->openedRule()->in($allowed));
    }

    /**
     * Gates the rest of the chain on already-read dependencies — see
     * {@see Rule::requires()}.
     */
    public function requires(mixed ...$values): self
    {
        return $this->with($this->openedRule()->requires(...$values));
    }

    /**
     * Appends a reusable custom constraint — see {@see Rule::rule()}.
     */
    public function rule(Constraint $constraint): self
    {
        return $this->with($this->openedRule()->rule($constraint));
    }

    /**
     * Appends a one-off custom check — see {@see Rule::satisfy()}.
     *
     * @param callable(mixed): bool             $check
     * @param null|class-string<InputException> $exceptionClass
     */
    public function satisfy(callable $check, string $message, ?string $exceptionClass = null): self
    {
        return $this->with($this->openedRule()->satisfy($check, $message, $exceptionClass));
    }

    /**
     * The strict terminal: returns the coerced value or throws the first
     * failure. An absent key always raises MissingInputException — leniency
     * is a terminal ({@see orNull()} / {@see orElse()}) — except a bare
     * bool chain, whose vocabulary makes absence a legitimate false.
     */
    public function value(): mixed
    {
        $outcome = $this->outcome();
        if ($outcome === null) {
            throw new MissingInputException('is required');
        }
        if ($outcome->failed()) {
            throw $outcome->failures[0];
        }

        return $outcome->value;
    }

    /**
     * The lenient terminal: returns the coerced value, or null when the key
     * is absent or any part of the chain failed — never throws for input.
     * (An absent bare bool still yields its legitimate false.).
     */
    public function orNull(): mixed
    {
        $outcome = $this->outcome();
        if ($outcome === null || $outcome->failed()) {
            return null;
        }

        return $outcome->value;
    }

    /**
     * The lenient terminal with a fallback: like {@see orNull()}, but
     * returns $default instead of null on absence or failure.
     */
    public function orElse(mixed $default): mixed
    {
        $outcome = $this->outcome();
        if ($outcome === null || $outcome->failed()) {
            return $default;
        }

        return $outcome->value;
    }

    /**
     * Reads the key literally and applies the chain: the shared front half
     * of every terminal. Null means the key is absent and the chain has no
     * opinion ({@see Rule::applyAbsent()}) — the terminal decides.
     */
    private function outcome(): ?Outcome
    {
        $rule = $this->openedRule();
        if (!Arr::hasKey($this->source, $this->key)) {
            return $rule->applyAbsent();
        }

        return $rule->apply($this->source[$this->key]);
    }

    /**
     * The opened chain, or the programmer-error signal when no coercer has
     * opened it: a terminal or verifier without a coercer is a
     * LogicException, not an input failure — the explicit raw read is str().
     */
    private function openedRule(): Rule
    {
        if ($this->rule === null) {
            throw new LogicException('Open the chain with a coercer first (str/int/float/num/bool) — the explicit raw read is str().');
        }

        return $this->rule;
    }

    /**
     * Opens the chain with its one coercer; a second coercer is the dual
     * programmer error.
     */
    private function open(Rule $rule): self
    {
        if ($this->rule !== null) {
            throw new LogicException('The chain is already open — exactly one coercer opens a chain.');
        }

        return new self($this->source, $this->key, $rule);
    }

    /**
     * The immutable copy with an evolved chain.
     */
    private function with(Rule $rule): self
    {
        return new self($this->source, $this->key, $rule);
    }
}
