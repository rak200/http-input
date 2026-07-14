<?php

declare(strict_types=1);

namespace Rak200\HttpInput;

use Rak200\HttpInput\Exception\InputException;

/**
 * The result of applying a {@see Rule} to one value: the coerced value plus
 * the failures the chain produced, none of them thrown (yet). The terminal
 * decides their fate — `value()` throws the first, `get()` records all,
 * `orNull()`/`orElse()` discard. When coercion itself fails the value is
 * null and the single coercion failure is the only entry.
 *
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class Outcome
{
    /**
     * @param list<InputException> $failures
     */
    public function __construct(
        public readonly mixed $value,
        public readonly array $failures = [],
    ) {}

    /**
     * Returns true when the chain produced at least one failure — the
     * counterpart of the validator-level `fails()`.
     */
    public function failed(): bool
    {
        return $this->failures !== [];
    }
}
