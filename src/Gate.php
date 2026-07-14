<?php

declare(strict_types=1);

namespace Rak200\HttpInput;

use Closure;
use Rak200\HttpInput\Exception\InputException;
use Rak200\HttpInput\Exception\InvalidInputException;

/**
 * The form-level cross-field gate (RFC 0013): obtained from
 * {@see Validator::requires()}, it records an assertion failure into the
 * validator only while *active* — i.e. when every dependency handed to
 * `requires()` was non-null. A missing dependency reports its own failure,
 * so a gated assertion never adds a spurious one. The chain-level sibling
 * is {@see Rule::requires()}.
 *
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class Gate
{
    /**
     * @param Closure(string, InputException): void $recorder the validator's collector
     * @param bool                                  $active   false when a dependency was null
     */
    public function __construct(
        private readonly Closure $recorder,
        private readonly bool $active,
    ) {}

    /**
     * Records $message under $field when the gate is active and $condition
     * is false — the sibling of the chain-level `satisfy()`, for rules not
     * tied to one field: `$form->requires($pw, $pwc)->assert('password_confirm',
     * $pw === $pwc, 'passwords must match')`. When given, $exceptionClass is
     * raised instead of InvalidInputException. Returns the gate, so several
     * assertions can share one set of dependencies.
     *
     * @param null|class-string<InputException> $exceptionClass
     */
    public function assert(string $field, bool $condition, string $message, ?string $exceptionClass = null): self
    {
        if ($this->active && !$condition) {
            $class = $exceptionClass ?? InvalidInputException::class;
            ($this->recorder)($field, new $class($message));
        }

        return $this;
    }
}
