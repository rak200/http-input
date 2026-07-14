<?php

declare(strict_types=1);

namespace Rak200\HttpInput;

/**
 * The verifier contract: check one already-coerced value and report.
 *
 * Implementations are field-agnostic — they see only the value, never the
 * key — so one constraint instance is reusable across fields. Every built-in
 * verifier implements this; custom ones plug into a chain via
 * `->rule(Constraint)`, or via the `->satisfy()` sugar that wraps a closure
 * into an anonymous Constraint.
 *
 * @author rak200 <rak.ricardo@windowslive.com>
 */
interface Constraint
{
    /**
     * Returns null when $value satisfies the constraint, or a {@see Violation}
     * (message + optional InputException subtype) describing how it fails.
     */
    public function check(mixed $value): ?Violation;
}
