# rak200/http-input

[![CI](https://github.com/rak200/http-input/actions/workflows/ci.yml/badge.svg)](https://github.com/rak200/http-input/actions/workflows/ci.yml)
[![Coverage](https://codecov.io/gh/rak200/http-input/graph/badge.svg)](https://codecov.io/gh/rak200/http-input)
[![Mutation testing](https://img.shields.io/badge/Infection-min%20MSI%20100%25-brightgreen?logo=php&logoColor=white)](infection.json5)
[![Latest tag](https://img.shields.io/github/v/tag/rak200/http-input?sort=semver)](https://github.com/rak200/http-input/tags)
[![PHP](https://img.shields.io/badge/php-8.4%2B-777bb4?logo=php&logoColor=white)](https://www.php.net/)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%20max-brightgreen?logo=php&logoColor=white)](https://phpstan.org/)
[![Code style](https://img.shields.io/badge/code%20style-PHP--CS--Fixer-blue?logo=php&logoColor=white)](.php-cs-fixer.dist.php)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue)](LICENSE)
[![SemVer](https://img.shields.io/badge/semver-2.0.0-blue)](https://semver.org/spec/v2.0.0.html)
[![Keep a Changelog](https://img.shields.io/badge/changelog-Keep%20a%20Changelog-orange)](CHANGELOG.md)

Strict, typed reading — and validation — of HTTP request data for PHP 8.4+.

Request data arrives as untrusted text in `$_GET`, `$_POST`, and friends. This package reads it through a **constraint chain**: exactly one *coercer* fixes the value's type, *verifiers* check it, and a *terminal* decides what a failure means — throw, fall back, or collect. Reading, verification, and validation are one mechanism with three endings:

```php
use Rak200\HttpInput\Input;

// throw — API / machine input (a 400 in the making)
$page = Input::from($_GET, 'page')->int()->min(1)->value();

// fall back — optional parameters
$q    = Input::from($_GET, 'q')->str()->orNull();
$size = Input::from($_GET, 'size')->int()->between(1, 100)->orElse(20);

// collect — forms (report every failure at once)
$form  = Input::validate($_POST);
$name  = $form->field('name')->str()->required()->minLen(2)->get();
$email = $form->field('email')->str()->required()->email()->get();
if ($form->fails()) {
    return $form->messages();   // ['email' => ['must be a valid e-mail'], ...]
}
```

The chain is byte-for-byte the same in all three modes — only the terminal changes. Coercion delegates to [`rak200/utils`](https://github.com/rak200/utils) (`Filter`, `Dt`, `Enum`); this package adds the orchestration, not a new primitive layer.

## Requirements

- PHP 8.4+
- [`rak200/utils`](https://github.com/rak200/utils) `^4.0` (installed automatically by Composer)

## Installation

```bash
composer require rak200/http-input
```

## The chain

**Coercers** open the chain and fix the type — one per chain:

```php
Input::from($src, 'name')->str();                    // string
Input::from($src, 'page')->int();                    // int      ('42'; '42.0' only via coerce())
Input::from($src, 'price')->float();                 // float    ('9.99')
Input::from($src, 'score')->num();                   // int|float, preserving which
Input::from($src, 'remember')->bool();               // bool     ('on'/absent, 'true'/'false')
Input::from($src, 'born')->date('Y-m-d');            // DateTimeImmutable
Input::from($src, 'at')->datetime();                 // DateTimeImmutable ('Y-m-d H:i:s')
Input::from($src, 'since')->timestamp();             // int (epoch seconds)
Input::from($src, 'role')->enum(Role::class);        // the enum case, by backed value
Input::from($src, 'tags')->listOf(Rule::str());      // list, each element checked
```

A bare coercer **asserts** — the value must already present as the type (`'42'` is an int, `'42.0'` is not). `->coerce()` opts into any *lossless* conversion: `'42.0'` → `42`, but `'42.5'` never becomes an int. An unchecked checkbox submits nothing, so for a bare `bool()` absence is a legitimate `false`, not missing data.

**Verifiers** check the coerced value — any number of them:

```php
->required()                    // presence (absent key → MissingInputException)
->min(1)->max(100)->between(1, 100)          // ordered range (numbers, dates)
->minLen(2)->maxLen(80)->lenBetween(2, 80)   // characters for strings, count for lists
->email()->url()->pattern('/^[a-z0-9-]+$/')  // format
->in(['s', 'm', 'l'])           // ad-hoc membership
->sameAs($password)             // cross-field match (an already-read value)
->nullable()                    // explicit null is accepted (JSON trees)
```

**Custom constraints** plug into the same chain:

```php
->satisfy(fn ($v) => $v % 3 === 0, 'must be divisible by 3')   // one-off
->rule(new DivisibleBy(3))                                     // reusable Constraint
```

Cross-field rules take the *already-read value* and gate on it — a missing dependency reports its own failure, never a spurious one:

```php
$pw  = $form->field('password')->str()->required()->minLen(8)->get();
$pwc = $form->field('password_confirm')->str()->required()->requires($pw)->sameAs($pw)->get();
```

## JSON bodies

`application/json` bodies are nested trees with already-typed leaves — a different shape from the flat bag. `Schema::object` and `Schema::listOf` compose the **same** rules into a schema; failures are keyed by the offending node's path:

```php
use Rak200\HttpInput\{Input, Rule, Schema};

$schema = Schema::object([
    'name'  => Rule::str()->required()->minLen(1),
    'items' => Schema::listOf(Schema::object([
        'sku' => Rule::str()->required(),
        'qty' => Rule::int()->min(1),
    ])),
]);   // unknown keys rejected by default; ->allowUnknownKeys() opts out

$result = Input::json($schema);        // reads php://input; malformed body → JsonException (400)
// …or, purely: $result = $schema->validate($decoded);

$result->fails();      // true
$result->messages();   // ['items.0.qty' => ['must be at least 1']]
$clean = $result->valid();   // the typed tree — or throws the first failure
```

A bare JSON leaf asserts the **decoded type** (`{"qty": "42"}` fails `Rule::int()` — a client bug, not something to silently accept); `->coerce()` opts into lossless conversion, same as on the flat bag.

## Failures are typed

Every failure is an `InputException` — `MissingInputException` (key absent) or `InvalidInputException` (present but failed), with per-constraint subtypes such as `OutOfRangeInputException` — so callers can branch on the failure kind. The terminal decides each failure's fate: `value()` throws the first, `get()` records all, `orNull()`/`orElse()` discard. Each failure also carries its field key — `$e->key()` returns the same key the collect bag files it under (`page`, `tags.0`), so a `catch` around several reads can tell which parameter failed, while `getMessage()` stays field-less.

## Superglobal shortcuts

`get` / `post` / `cookie` / `server` / `env` / `request` are sugar over `from()` and return an **accessor**, not a value — the only place the library touches a superglobal:

```php
$page     = Input::get('page')->int()->min(1)->value();
$remember = Input::post('remember')->bool()->value();
```

## Documentation

Per-class reference with runnable examples lives in [`docs/`](docs/README.md).

## Roadmap

Planned work is tracked in [`ROADMAP.md`](ROADMAP.md) — self-contained follow-up items surfaced by release reviews.

## Conventions

- Final classes, strict types everywhere (`declare(strict_types=1)`).
- The chain is immutable — every chain call returns a new instance; rules are reusable values.
- Failures are input exceptions; misuse of the chain itself (a terminal before a coercer, two coercers) is a `LogicException` — a programmer error, never an input failure.
- Keys are looked up literally, never as dot-paths.
- The core is pure (source array in); only the superglobal shortcuts touch superglobals.

## Versioning

Follows [Semantic Versioning](https://semver.org). Pre-1.0: minor versions may break.

## Licence

MIT
