<?php

declare(strict_types=1);

namespace Rak200\HttpInput;

use Rak200\HttpInput\Exception\InputException;
use Rak200\Utils\Arr;

/**
 * The collect-mode context (RFC 0013's Mode 3): many accessors bound to one
 * shared error bag, so a whole payload is checked and every failure is
 * reported at once — forms, not fail-fast APIs.
 *
 * {@see field()} returns an {@see Accessor} whose `get()` terminal records
 * failures here instead of throwing; {@see requires()} opens the form-level
 * cross-field gate ({@see Gate::assert()}). Unlike Rule and Accessor the
 * validator is deliberately stateful — it *is* the bag: {@see fails()},
 * {@see errors()} (per-field exception lists), {@see messages()} (the flat
 * string view), and {@see values()} (the clean payload, one entry per
 * declared field) read it back.
 *
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class Validator
{
    /**
     * @var array<string, list<InputException>>
     */
    private array $errors = [];

    /**
     * @var array<string, mixed>
     */
    private array $values = [];

    /**
     * Binds the validator to the payload under check. Prefer the
     * {@see Input::validate()} facade; this constructor is its backing.
     *
     * @param array<array-key, mixed> $source
     */
    public function __construct(
        private readonly array $source,
    ) {}

    /**
     * Declares a field: returns an {@see Accessor} on `(source, $key)` whose
     * `get()` terminal records into this validator. The chain between
     * `field()` and `get()` is byte-for-byte the one the strict terminals
     * use.
     */
    public function field(string $key): Accessor
    {
        return new Accessor($this->source, $key, null, $this->record(...));
    }

    /**
     * Opens the form-level cross-field gate over already-read values: the
     * returned {@see Gate}'s `assert()` records only when every dependency
     * is non-null — a missing dependency reports its own failure, so the
     * assertion adds no spurious one.
     */
    public function requires(mixed ...$values): Gate
    {
        return new Gate($this->recordError(...), !Arr::contains($values, null));
    }

    /**
     * Returns true when at least one failure has been recorded.
     */
    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /**
     * The structured bag: every recorded failure, keyed by field, in
     * declaration order.
     *
     * @return array<string, list<InputException>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * The flat view of {@see errors()}: the failure messages, keyed by
     * field — `['email' => ['must be a valid e-mail'], ...]`.
     *
     * @return array<string, list<string>>
     */
    public function messages(): array
    {
        return Arr::map($this->errors, self::failureMessages(...));
    }

    /**
     * The clean payload: one entry per declared field with its best-effort
     * value — the coerced value, null for a skipped or uncoercible field,
     * false for an absent bare bool. Meant to be read after {@see fails()}
     * says no failure was recorded.
     *
     * @return array<string, mixed>
     */
    public function values(): array
    {
        return $this->values;
    }

    /**
     * @param list<InputException> $failures
     *
     * @return list<string>
     */
    private static function failureMessages(array $failures): array
    {
        return Arr::map(
            $failures,
            static fn (InputException $failure): string => $failure->getMessage(),
        );
    }

    /**
     * The collector behind `field()->...->get()`: stores the field's
     * best-effort value and appends its failures to the bag. A failure that
     * carries a relative path ({@see InputException::at()} — a listOf
     * element) is keyed by it: `tags.0`. Each failure is bound to that same
     * key ({@see InputException::forKey()}), so it carries its own field.
     *
     * @param list<InputException> $failures
     */
    private function record(string $key, mixed $value, array $failures): void
    {
        $this->values[$key] = $value;
        foreach ($failures as $failure) {
            $fullKey = $failure->keyUnder($key);
            $this->errors[$fullKey][] = $failure->forKey($fullKey);
        }
    }

    /**
     * The collector behind {@see Gate::assert()}: appends one failure to
     * the bag, bound to $field, without touching the field's value.
     */
    private function recordError(string $key, InputException $failure): void
    {
        $this->errors[$key][] = $failure->forKey($key);
    }
}
