<?php

declare(strict_types=1);

namespace Rak200\HttpInput;

/**
 * The static entry facade to the constraint chain (RFC 0013).
 *
 * {@see from()} binds `(source, key)` into an {@see Accessor} — the pure
 * core: exactly one coercer opens the chain, verifiers follow, and a
 * terminal decides every failure's fate — `value()` throws the first,
 * `orNull()`/`orElse()` discard and fall back, `get()` records into a
 * validator. {@see validate()} opens that collect mode over a whole payload
 * ({@see Validator}).
 *
 * The superglobal shortcuts ({@see get()}, {@see post()}, {@see cookie()},
 * {@see server()}, {@see env()}, {@see request()}) are pure sugar over
 * `from()` — they return an accessor, not a pre-terminated value, so they
 * stay uniform with the chain and leniency remains the caller's explicit
 * terminal. They are the only place the library touches a superglobal;
 * everything else reads the array handed to it.
 *
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class Input
{
    private function __construct() {}

    /**
     * Binds `(source, key)` into an {@see Accessor} — the entry to the
     * constraint chain: `Input::from($_GET, 'page')->int()->min(1)->value()`.
     * The key is looked up literally (never as a dot-path).
     *
     * @param array<array-key, mixed> $source
     */
    public static function from(array $source, string $key): Accessor
    {
        return new Accessor($source, $key);
    }

    /**
     * Opens collect mode over a payload: `Input::validate($_POST)` returns a
     * {@see Validator} whose `field()` accessors record failures into a
     * shared bag via their `get()` terminal — the whole form is checked and
     * every failure reported at once.
     *
     * @param array<array-key, mixed> $source
     */
    public static function validate(array $source): Validator
    {
        return new Validator($source);
    }

    /**
     * Binds $key within `$_GET`: `Input::get('page')->int()->min(1)->value()`.
     */
    public static function get(string $key): Accessor
    {
        return self::from($_GET, $key);
    }

    /**
     * Binds $key within `$_POST`.
     */
    public static function post(string $key): Accessor
    {
        return self::from($_POST, $key);
    }

    /**
     * Binds $key within `$_COOKIE`.
     */
    public static function cookie(string $key): Accessor
    {
        return self::from($_COOKIE, $key);
    }

    /**
     * Binds $key within `$_SERVER`.
     */
    public static function server(string $key): Accessor
    {
        return self::from($_SERVER, $key);
    }

    /**
     * Binds $key within `$_ENV`.
     */
    public static function env(string $key): Accessor
    {
        return self::from($_ENV, $key);
    }

    /**
     * Binds $key within `$_REQUEST`. Kept but discouraged: `$_REQUEST` mixes
     * GET, POST, and COOKIE with ini-dependent precedence — prefer the
     * specific source.
     */
    public static function request(string $key): Accessor
    {
        return self::from($_REQUEST, $key);
    }
}
