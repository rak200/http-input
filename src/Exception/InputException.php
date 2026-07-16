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
 * The terminal that materialises a failure binds its **field key** — the
 * absolute, bag-style identifier (`page`, `tags.0`) — via {@see forKey()};
 * {@see key()} reads it back, so a `catch` wrapping several reads can tell
 * which parameter failed. The message itself stays field-agnostic.
 *
 * @author rak200 <rak.ricardo@windowslive.com>
 */
abstract class InputException extends RuntimeException
{
    private ?string $at = null;

    private ?string $key = null;

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

    /**
     * The field key bound at the terminal — the same key the collect bag
     * files this failure under (`page`, `tags.0`), or null before a terminal
     * binds it (a free-standing {@see Rule} failure). `getMessage()` stays
     * field-less; this is where the field lives.
     */
    public function key(): ?string
    {
        return $this->key;
    }

    /**
     * Binds $key as this failure's field key — called by the terminal that
     * materialises the failure (`value()`, the validator, a schema walk) the
     * moment it decides the failure's fate. Returns the failure, so a throw
     * site can bind inline.
     */
    public function forKey(string $key): static
    {
        $this->key = $key;

        return $this;
    }

    /**
     * The bag-style key this failure takes under $parent: `$parent` for the
     * field's own failure, `"{$parent}.{$at}"` when it carries a relative
     * path ({@see at()} — a `listOf` element). The one place the top-level
     * key and the nested path compose.
     */
    public function keyUnder(string $parent): string
    {
        return $this->at === null ? $parent : "{$parent}.{$this->at}";
    }
}
