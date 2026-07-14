# Input

[← Reference](README.md)

The static entry facade to the constraint chain: `from` binds `(source, key)` into an [`Accessor`](accessor.md), `validate` opens collect mode over a payload ([`Validator`](validator.md)), and `json` validates the request body against a [`Schema`](schema.md). Everything else is pure — only the superglobal shortcuts (sugar over `from`) and `json` (`php://input`) touch the request environment.

```php
use Rak200\HttpInput\Input;
```

## Contents

- [`from`](#from)
- [`validate`](#validate)
- [`json`](#json)
- [`get` / `post` / `cookie` / `server` / `env` / `request`](#get--post--cookie--server--env--request)

---

## `from`

Binds `$key` within `$source` and returns an [`Accessor`](accessor.md) — the chain then opens with a coercer and ends at a terminal. The key is looked up **literally**, never as a dot-path.

```php
Input::from($_GET, 'page')->int()->min(1)->value();     // 3      (or throws)
Input::from($_GET, 'q')->str()->orNull();               // 'abc' | null
Input::from(['a.b' => '5'], 'a.b')->int()->value();     // 5      (literal key)
```

[↑ Back to top](#input)

---

## `validate`

Opens collect mode: returns a [`Validator`](validator.md) whose `field()` accessors record failures into a shared bag via their `get()` terminal, so the whole payload is checked and every failure reported at once.

```php
$form = Input::validate($_POST);

$name  = $form->field('name')->str()->required()->minLen(2)->get();
$email = $form->field('email')->str()->required()->email()->get();

if ($form->fails()) {
    $form->messages();   // ['email' => ['must be a valid e-mail']]
}
```

[↑ Back to top](#input)

---

## `json`

Reads the raw request body (`php://input`), decodes it (`Json::decode` forces `JSON_THROW_ON_ERROR`), and validates the tree against a [`Schema`](schema.md), returning a [`Result`](schema.md#result) with path-keyed failures. A malformed body throws `JsonException` — a 400 in its own right, deliberately distinct from schema errors. The pure equivalent is `$schema->validate($decoded)`.

```php
$schema = Schema::object(['qty' => Rule::int()->min(1)]);   // combinators: schema.md

try {
    $result = Input::json($schema);
} catch (JsonException) {
    // malformed body — reject before any schema concern
}

$result->messages();   // ['qty' => ['must be at least 1']]
```

[↑ Back to top](#input)

---

## `get` / `post` / `cookie` / `server` / `env` / `request`

Superglobal shortcuts over `$_GET`, `$_POST`, `$_COOKIE`, `$_SERVER`, `$_ENV`, `$_REQUEST`. Each returns an **accessor**, not a pre-terminated value — uniform with the chain, so the coercer and the terminal stay the caller's explicit choice.

```php
Input::get('page')->int()->min(1)->value();      // 3
Input::post('remember')->bool()->value();        // false  (unchecked checkbox → absent → false)
Input::cookie('session')->str()->orNull();       // 'abc' | null
Input::server('HTTP_HOST')->str()->value();      // 'example.com'
Input::env('APP_ENV')->str()->orElse('prod');    // 'prod' when unset
```

`request` is kept but **discouraged** — `$_REQUEST` mixes GET, POST, and COOKIE with ini-dependent precedence; prefer the specific source. `$_FILES` is out of scope (uploads are structured data, not a string read).

[↑ Back to top](#input)
