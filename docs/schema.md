# Schema & Result

[← Reference](README.md)

Structured JSON validation (RFC 0014): `Schema::object` and `Schema::listOf` compose [`Rule`](rule.md) leaves into a tree; validating a decoded payload yields a `Result` with **path-keyed** failures. Leaves run with decoded-tree semantics — a bare leaf asserts the decoded PHP type; `coerce()` re-enables lossless conversion.

```php
use Rak200\HttpInput\{Input, Rule, Schema};
```

## Contents

- [`object` / `listOf`](#object--listof)
- [`allowUnknownKeys`](#allowunknownkeys)
- [`validate` — the pure core](#validate--the-pure-core)
- [`Input::json` — the request-body shortcut](#inputjson--the-request-body-shortcut)
- [`Result`](#result)

---

## `object` / `listOf`

The two structural combinators. `object(shape)` maps each key to a leaf rule or a nested schema; `listOf(element)` requires a homogeneous list. Presence follows the leaf: `required()` rejects an absent key, `nullable()` accepts a present null, anything else is optional (skipped as null). An absent nested object is skipped as a whole.

```php
$schema = Schema::object([
    'name'    => Rule::str()->required()->minLen(1),
    'email'   => Rule::str()->required()->email(),
    'age'     => Rule::int()->between(0, 120),          // optional; checked if present
    'phone'   => Rule::str()->nullable()->minLen(8),    // explicit null accepted → null
    'address' => Schema::object([
        'city'    => Rule::str()->required(),
        'country' => Rule::str()->required(),
    ]),
    'tags'    => Schema::listOf(Rule::str()->minLen(1)),
    'items'   => Schema::listOf(Schema::object([
        'sku' => Rule::str()->required(),
        'qty' => Rule::int()->min(1),
    ])),
]);
```

JSON leaves assert the **decoded type**: `{"qty": "42"}` fails a bare `Rule::int()` (a string is not a JSON integer) — a client bug, not something to silently accept. Opt into leniency per leaf with `Rule::int()->coerce()`. A JSON bool has no checkbox convention: an absent bare `bool()` follows the normal presence rules, not the HTML `false`.

[↑ Back to top](#schema--result)

---

## `allowUnknownKeys`

Unknown keys are **rejected by default** — a key present in the payload with no rule is an error keyed by its path, the safe default for closed APIs. Opt out per object; either way an unknown key is never copied into the clean tree.

```php
Schema::object(['name' => Rule::str()])->validate(['name' => 'Ada', 'x' => 1])
    ->messages();                                            // ['x' => ['is not allowed']]

Schema::object(['name' => Rule::str()])->allowUnknownKeys()
    ->validate(['name' => 'Ada', 'x' => 1])->fails();        // false — ignored, not copied
```

[↑ Back to top](#schema--result)

---

## `validate` — the pure core

Validates an **already-decoded** tree (associative arrays, exactly as `Json::decode` returns them) and returns a [`Result`](#result). Failures recurse with a path prefix, so a caller can point at the exact offending node; a malformed root is keyed by the empty path.

```php
$result = $schema->validate($decoded);

$result->messages();
// ['items.0.qty' => ['must be at least 1'], 'address.city' => ['is required']]
```

Known decode corner, accepted: under `$assoc = true` the empty object `{}` and the empty array `[]` both decode to `[]`, so `{}` passes a `listOf` as an empty list and `[]` passes an all-optional `object`.

[↑ Back to top](#schema--result)

---

## `Input::json` — the request-body shortcut

Reads `php://input`, decodes it via `Json::decode` (which forces `JSON_THROW_ON_ERROR`), and validates. A malformed body surfaces as a `JsonException` — a 400 in its own right, deliberately distinct from schema errors.

```php
try {
    $result = Input::json($schema);
} catch (JsonException) {
    // malformed body → 400, before any schema concern
}
```

[↑ Back to top](#schema--result)

---

## `Result`

The validation outcome: the [`Validator`](validator.md) reporting surface, path-keyed, plus `valid()` — the fail-fast terminal over a tree.

```php
$result->fails();      // bool
$result->errors();     // ['items.0.qty' => [OutOfRangeInputException], ...]
$result->messages();   // ['items.0.qty' => ['must be at least 1'], ...]
$result->values();     // the clean, fully typed tree
$result->valid();      // the clean tree — or throws the first InputException
```

Both of RFC 0013's terminal styles carry over: collect (read the bag) and throw (`valid()`). Every failure carries its path as its key — `$e->key()` returns the same path the bag uses (`items.0.qty`), so a failure thrown by `valid()` stays self-describing (see [Exceptions](exceptions.md#key--forkey)).

[↑ Back to top](#schema--result)
