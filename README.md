# rak200/http-input

Typed, safe reading of HTTP request data for PHP 8.4+.

Request data arrives as untrusted strings in `$_GET`, `$_POST`, and friends. This package reads a key, coerces it to the type you ask for, and falls back to a default when the key is missing or the value cannot be represented — no exceptions, no boilerplate `isset()` ladders.

It is a thin, single-class companion to [`rak200/utils`](https://github.com/rak200/utils): the coercion is delegated to `Rak200\Utils\Filter`, keeping `utils` pure and this package focused on the request layer.

## Requirements

- PHP 8.4+
- [`rak200/utils`](https://github.com/rak200/utils) `^1.10` (installed automatically by Composer)

## Installation

```bash
composer require rak200/http-input
```

## Usage

```php
use Rak200\HttpInput\Input;
```

### Pure core — read from any source array

The typed accessors take the source array as their first argument, so they are pure and testable, and work directly on a superglobal:

```php
Input::str($_POST, 'name');                 // ?string  ('' stays '', arrays → default)
Input::int($_GET, 'page', 1);               // int      ('42' → 42, 'abc' → 1)
Input::int($_GET, 'page', 1, min: 1, max: 100);   // clamped into [1, 100]
Input::float($_POST, 'price');              // ?float
Input::bool($_POST, 'remember');            // ?bool    ('on'/'yes'/'1' → true)
Input::array($_POST, 'tags', []);           // array<...> (a name[] field)

Input::has($_GET, 'q');                     // bool — key present (even if null)
Input::all($_POST);                         // the whole bag, unchanged
```

Coercion rules come from [`Filter::to*`](https://github.com/rak200/utils/blob/master/docs/filter.md): strings are trimmed, `"42"` → `42`, `"on"`/`"yes"`/`"1"` → `true`, and anything that cannot be represented yields the default you passed.

### Convenience shortcuts — read a string from a superglobal

For the common "fetch one parameter" case:

```php
Input::get('q');                  // ?string from $_GET
Input::post('name', 'Anonymous'); // ?string from $_POST, with a default
Input::request('id');             // $_REQUEST
Input::cookie('session');         // $_COOKIE
Input::server('HTTP_HOST');       // $_SERVER
Input::env('APP_ENV');            // $_ENV
```

For a **typed** read from a superglobal, call the core directly — no separate `getInt`/`postBool` methods needed:

```php
$page     = Input::int($_GET, 'page', 1, min: 1);
$remember = Input::bool($_POST, 'remember', false);
```

## Documentation

Per-method reference with runnable examples lives in [`docs/`](docs/README.md).

## Conventions

- `Input` is `final` with a `private` constructor — a pure static API, no instances.
- Strict types everywhere (`declare(strict_types=1)`).
- No method throws: a missing key or an uncoercible value returns the caller-supplied default.
- The core is pure (source array in); only the `get`/`post`/`request`/`cookie`/`server`/`env` shortcuts touch superglobals.

## Versioning

Follows [Semantic Versioning](https://semver.org).

## Licence

MIT
