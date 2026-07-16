# Validator & Gate

[← Reference](README.md)

Collect mode: many accessors bound to one shared error bag, so a whole payload is checked and **every** failure reported at once — forms, not fail-fast APIs. `Validator` is deliberately stateful (it *is* the bag); `Gate` is the form-level cross-field gate its `requires()` opens.

```php
use Rak200\HttpInput\Input;
```

## Contents

- [`field`](#field)
- [`fails` / `errors` / `messages` / `values`](#fails--errors--messages--values)
- [`requires` → `Gate::assert`](#requires--gateassert)
- [A whole form](#a-whole-form)

---

## `field`

Declares a field: an [`Accessor`](accessor.md) on `(source, key)` whose `get()` terminal records into this validator instead of throwing. The chain between `field()` and `get()` is byte-for-byte the one the strict terminals use.

```php
$form = Input::validate($_POST);
$age  = $form->field('age')->int()->between(18, 120)->get();   // best-effort value
```

An absent optional field is skipped (value `null`, no error); `required()` turns absence into a Missing failure; an absent bare `bool` is the legitimate `false`. A `listOf` element failure is keyed by its index: `tags.0`.

[↑ Back to top](#validator--gate)

---

## `fails` / `errors` / `messages` / `values`

The reporting surface, read after the fields are declared:

```php
$form = Input::validate(['email' => 'nope', 'tags' => ['s', 'x']]);
$form->field('email')->str()->required()->email()->get();
$form->field('name')->str()->required()->get();
$form->field('tags')->listOf(Rule::str()->in(['s', 'm', 'l']))->get();

$form->fails();      // true
$form->errors();     // ['email' => [FormatInputException], 'name' => [MissingInputException],
                     //  'tags.1' => [MembershipInputException]]
$form->messages();   // ['email' => ['must be a valid e-mail'], 'name' => ['is required'],
                     //  'tags.1' => ['must be one of s, m, l']]
$form->values();     // ['email' => 'nope', 'name' => null, 'tags' => ['s', 'x']]
```

- `errors()` — the structured bag: every failure keyed by field (or by element path), multiple failures per field in declaration order; each exception also carries that key via `key()` (see [Exceptions](exceptions.md#key--forkey)).
- `messages()` — the flat string view of the same bag.
- `values()` — the clean payload: one entry per declared field with its best-effort value; meant to be read once `fails()` says nothing was recorded.

[↑ Back to top](#validator--gate)

---

## `requires` → `Gate::assert`

Rules not tied to one field use the form-level gate: `requires(...$values)` returns a `Gate` that is **active** only when every dependency is non-null; `assert($field, $condition, $message, $exceptionClass = null)` records the failure under `$field` when active and the condition is false. A missing dependency reports its own failure, so a gated assertion never adds a spurious one. `assert` returns the gate — several assertions can share one set of dependencies.

```php
$pw  = $form->field('password')->str()->required()->get();
$pwc = $form->field('password_confirm')->str()->required()->get();

$form->requires($pw, $pwc)->assert('password_confirm', $pw === $pwc, 'passwords must match');
```

The chain-level sibling is [`Rule::requires()`](rule.md#in--sameas--requires), which gates a single field's own chain.

[↑ Back to top](#validator--gate)

---

## A whole form

```php
$form = Input::validate($_POST);

$name  = $form->field('name')->str()->required()->minLen(2)->maxLen(80)->get();
$email = $form->field('email')->str()->required()->email()->get();
$age   = $form->field('age')->int()->between(18, 120)->get();
$pw    = $form->field('password')->str()->required()->minLen(8)->get();
$pwc   = $form->field('password_confirm')->str()->required()->requires($pw)->sameAs($pw)->get();

if ($form->fails()) {
    return $form->messages();   // every failure, field-keyed, at once
}
$clean = $form->values();       // the typed, clean payload
```

[↑ Back to top](#validator--gate)
