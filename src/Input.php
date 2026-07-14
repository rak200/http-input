<?php

declare(strict_types=1);

namespace Rak200\HttpInput;

use Rak200\Utils\Arr;
use Rak200\Utils\Filter;

/**
 * Typed, safe reading of HTTP request data.
 *
 * The core ({@see str()}, {@see int()}, {@see float()}, {@see bool()},
 * {@see array()}, {@see has()}, {@see all()}) is pure: it reads a key from a
 * source array passed in by the caller, coerces it to the requested type via
 * {@see Filter}, and returns a caller-supplied `$default` when the key is
 * missing or the value cannot be represented. Because the source is a plain
 * array, the core works directly on a superglobal — `Input::int($_GET, 'page', 1)`.
 *
 * On top of that sit thin convenience shortcuts ({@see get()}, {@see post()},
 * {@see request()}, {@see cookie()}, {@see server()}, {@see env()}) that read a
 * string from the matching superglobal for the common "fetch one parameter"
 * case. For a typed read from a superglobal, call the core directly with the
 * superglobal as the source.
 *
 * {@see from()} is the entry to the 0.2.0 constraint chain (RFC 0013) — a
 * strict/lenient read through an {@see Accessor} — which will replace this
 * static API when the redesign completes.
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
     * Reads $key from $source as a string (coerced via {@see Filter::toStr()}),
     * or $default when the key is absent or the value cannot be coerced.
     *
     * @param array<array-key, mixed> $source
     */
    public static function str(array $source, string $key, ?string $default = null): ?string
    {
        if (!Arr::has($source, $key)) {
            return $default;
        }

        return Filter::toStr($source[$key], $default);
    }

    /**
     * Reads $key from $source as an int (coerced via {@see Filter::toInt()}), or
     * $default when the key is absent or the value cannot be coerced. When given,
     * $min and $max clamp the result (each bound applied independently); a null
     * result is never clamped.
     *
     * @param array<array-key, mixed> $source
     */
    public static function int(
        array $source,
        string $key,
        ?int $default = null,
        ?int $min = null,
        ?int $max = null,
    ): ?int {
        $value = Arr::has($source, $key) ? Filter::toInt($source[$key], $default) : $default;

        return self::clampInt($value, $min, $max);
    }

    /**
     * Reads $key from $source as a float (coerced via {@see Filter::toFloat()}),
     * or $default when the key is absent or the value cannot be coerced. When
     * given, $min and $max clamp the result (each bound applied independently);
     * a null result is never clamped.
     *
     * @param array<array-key, mixed> $source
     */
    public static function float(
        array $source,
        string $key,
        ?float $default = null,
        ?float $min = null,
        ?float $max = null,
    ): ?float {
        $value = Arr::has($source, $key) ? Filter::toFloat($source[$key], $default) : $default;
        if ($value === null) {
            return null;
        }
        if ($min !== null && $value < $min) {
            $value = $min;
        }
        if ($max !== null && $value > $max) {
            $value = $max;
        }

        return $value;
    }

    /**
     * Reads $key from $source as a bool (coerced via {@see Filter::toBool()},
     * which understands HTML-form values like `"on"`/`"yes"`/`"1"`), or $default
     * when the key is absent or the value cannot be coerced.
     *
     * @param array<array-key, mixed> $source
     */
    public static function bool(array $source, string $key, ?bool $default = null): ?bool
    {
        if (!Arr::has($source, $key)) {
            return $default;
        }

        return Filter::toBool($source[$key], $default);
    }

    /**
     * Reads $key from $source as an array (e.g. a `name[]` field), or $default
     * when the key is absent or the value is not an array. No coercion is applied
     * to the elements.
     *
     * @param array<array-key, mixed>      $source
     * @param null|array<array-key, mixed> $default
     *
     * @return null|array<array-key, mixed>
     */
    public static function array(array $source, string $key, ?array $default = null): ?array
    {
        if (!Arr::has($source, $key) || !Arr::is($source[$key])) {
            return $default;
        }

        return $source[$key];
    }

    /**
     * Returns true if $key is present in $source (including when its value is null).
     *
     * @param array<array-key, mixed> $source
     */
    public static function has(array $source, string $key): bool
    {
        return Arr::has($source, $key);
    }

    /**
     * Returns $source unchanged — a readable way to name "the whole request bag".
     *
     * @param array<array-key, mixed> $source
     *
     * @return array<array-key, mixed>
     */
    public static function all(array $source): array
    {
        return $source;
    }

    /**
     * Reads $key from `$_GET` as a string, or $default when absent/uncoercible.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        return self::str($_GET, $key, $default);
    }

    /**
     * Reads $key from `$_POST` as a string, or $default when absent/uncoercible.
     */
    public static function post(string $key, ?string $default = null): ?string
    {
        return self::str($_POST, $key, $default);
    }

    /**
     * Reads $key from `$_REQUEST` as a string, or $default when absent/uncoercible.
     */
    public static function request(string $key, ?string $default = null): ?string
    {
        return self::str($_REQUEST, $key, $default);
    }

    /**
     * Reads $key from `$_COOKIE` as a string, or $default when absent/uncoercible.
     */
    public static function cookie(string $key, ?string $default = null): ?string
    {
        return self::str($_COOKIE, $key, $default);
    }

    /**
     * Reads $key from `$_SERVER` as a string, or $default when absent/uncoercible.
     */
    public static function server(string $key, ?string $default = null): ?string
    {
        return self::str($_SERVER, $key, $default);
    }

    /**
     * Reads $key from `$_ENV` as a string, or $default when absent/uncoercible.
     */
    public static function env(string $key, ?string $default = null): ?string
    {
        return self::str($_ENV, $key, $default);
    }

    /**
     * Clamps $value to $min/$max when each is given; leaves a null result alone.
     */
    private static function clampInt(?int $value, ?int $min, ?int $max): ?int
    {
        if ($value === null) {
            return null;
        }
        if ($min !== null && $value < $min) {
            $value = $min;
        }
        if ($max !== null && $value > $max) {
            $value = $max;
        }

        return $value;
    }
}
