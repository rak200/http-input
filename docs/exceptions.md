# Exceptions

[← Reference](README.md)

The failure model (namespace `Rak200\HttpInput\Exception`): a failure is an `InputException` that has not (yet) been thrown — the terminal decides its fate. Two base kinds sit under the abstract root, with per-constraint subtypes below the second, so callers can branch on the failure *kind* while `catch (InputException)` stays the library-scoped catch.

```php
use Rak200\HttpInput\Exception\InputException;
```

## Contents

- [The hierarchy](#the-hierarchy)
- [`at` / `nest`](#at--nest)
- [`key` / `forKey`](#key--forkey)
- [Input failures vs programmer errors](#input-failures-vs-programmer-errors)

---

## The hierarchy

```
RuntimeException
└── InputException (abstract)
    ├── MissingInputException          the key is absent
    └── InvalidInputException          present, but failed the chain
        ├── OutOfRangeInputException   min / max / between
        ├── LengthInputException       minLen / maxLen / lenBetween
        ├── FormatInputException       email / url / pattern
        ├── MismatchInputException     sameAs
        └── MembershipInputException   in
```

`MissingInputException` and `InvalidInputException` are deliberately distinct: *"you didn't send `page`"* and *"you sent `page=abc`"* are different failures. Coercion failures raise the base `InvalidInputException`; custom constraints may name any `InputException` subtype via [`Violation`](contracts.md#violation).

```php
try {
    $page = Input::from($_GET, 'page')->int()->min(1)->value();
} catch (MissingInputException $e) {
    // absent — 400 'page is required'
} catch (InvalidInputException $e) {
    // present but bad — 400 with $e->getMessage()
}
```

Messages are field-agnostic predicates (`'must be at least 1'`) so the collect bag can key them by field — see `Validator::messages()`.

[↑ Back to top](#exceptions)

---

## `at` / `nest`

A failure raised inside a nested structure carries the offending node's path *relative to the field*: `at()` returns it (`'0'`, `'0.qty'`), or null for the field's own failure; `nest($segment)` prepends a segment as the failure bubbles out — how a `listOf` element failure becomes `tags.0` in the validator's bag.

```php
$outcome = Rule::listOf(Rule::int())->apply(['1', 'x']);
$outcome->failures[0]->at();   // '1'
```

[↑ Back to top](#exceptions)

---

## `key` / `forKey`

The terminal that materialises a failure binds its **field key** — the absolute, bag-style identifier. `key()` returns it (`'page'`, `'tags.0'`), or `null` before a terminal binds it (a free-standing [`Rule`](rule.md) failure). It is the *same* key the collect bag files the failure under, so throw mode and collect mode agree on a failure's identity — a `catch` wrapping several reads can tell **which** parameter failed, while `getMessage()` stays field-less.

```php
try {
    $page = Input::from($_GET, 'page')->int()->min(1)->value();
    $size = Input::from($_GET, 'size')->int()->max(100)->value();
} catch (InputException $e) {
    $e->key();          // 'page' — which read failed
    $e->getMessage();   // 'must be at least 1' — still field-less
}
```

`key()` is the absolute counterpart the relative `at()` composes into: a nested failure is keyed `field.at` (`tags.0`), matching the validator's bag and `Result`'s path keys. `forKey($key)` performs the binding — the terminals call it (`value()`, the validator, a schema walk); `keyUnder($parent)` is the compose helper it uses: `$parent`, or `"$parent.$at"` when a relative path is present.

[↑ Back to top](#exceptions)

---

## Input failures vs programmer errors

Only *input* raises an `InputException`. Misusing the chain itself — a terminal or verifier before a coercer, two coercers, `get()` outside collect mode, a pure enum without `byName` — throws SPL `LogicException` instead: those are bugs to fix, not failures to handle.

[↑ Back to top](#exceptions)
