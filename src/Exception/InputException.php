<?php

declare(strict_types=1);

namespace Rak200\HttpInput\Exception;

use RuntimeException;

/**
 * Base type of every input failure the library raises.
 *
 * A failure is an InputException that has not (yet) been thrown: the chain's
 * terminal decides its fate — `value()` throws the first, `get()` records all
 * into the validator, `orNull()`/`orElse()` discard. Catching this type is the
 * library-scoped catch: it covers {@see MissingInputException},
 * {@see InvalidInputException}, and every per-constraint subtype.
 *
 * A failure raised inside a nested structure (a `listOf` element, a schema
 * node) carries the offending node's path relative to the field — see
 * {@see at()} / {@see nest()}; the validator keys the error bag with it
 * (`tags.0`, `items.0.qty`).
 *
 * @author rak200 <rak.ricardo@windowslive.com>
 */
abstract class InputException extends RuntimeException
{
    private ?string $at = null;

    /**
     * The path of the offending node relative to the field (`'0'`,
     * `'0.qty'`), or null when the failure is the field's own.
     */
    public function at(): ?string
    {
        return $this->at;
    }

    /**
     * Prepends $segment to the relative path — called as the failure bubbles
     * out of a nested structure, so the segments compose outside-in:
     * `nest('qty')` then `nest('0')` reads back as `0.qty`.
     */
    public function nest(string $segment): static
    {
        $this->at = $this->at === null ? $segment : $segment . '.' . $this->at;

        return $this;
    }
}
