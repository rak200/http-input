# Accessor

[← Reference](README.md)

A [`Rule`](rule.md) bound to `(source, key)`, plus the **terminals**. The accessor exposes the whole chain API — coercers, verifiers, flags — and ends the read: `value()` throws the first failure, `orNull()`/`orElse()` discard and fall back, `get()` records into a [`Validator`](validator.md). Obtain one from `Input::from()` (or `$form->field()` in collect mode); like `Rule`, it is immutable.

```php
use Rak200\HttpInput\Input;
```

## Contents

- [The chain, bound](#the-chain-bound)
- [`value`](#value)
- [`orNull` / `orElse`](#ornull--orelse)
- [`get`](#get)
- [Chain grammar — `LogicException`](#chain-grammar--logicexception)

---

## The chain, bound

Every chain method of [`Rule`](rule.md) exists on the accessor as a one-line delegation — same names, same semantics: `str` / `int` / `float` / `num` / `bool` / `date` / `time` / `datetime` / `timestamp` / `enum` / `listOf` open the chain; `coerce` / `required` / `nullable`, the verifiers, `requires`, and `rule` / `satisfy` follow. Keys are looked up **literally** (never dot-paths), with flat-request-bag semantics: a bare coercer asserts the value's text format.

```php
Input::from($_GET, 'page')->int()->min(1);       // an accessor, chain open, no terminal yet
```

[↑ Back to top](#accessor)

---

## `value`

The strict terminal: the coerced value, or the **first** failure thrown. An absent key always raises `MissingInputException` — leniency is a terminal, not a default — except a bare `bool`, whose vocabulary makes absence a legitimate `false`. The thrown failure carries the field key — `$e->key()` names which read failed (see [Exceptions](exceptions.md#key--forkey)).

```php
Input::from(['page' => '3'], 'page')->int()->min(1)->value();   // 3
Input::from([], 'page')->int()->value();                        // throws MissingInputException
Input::from(['page' => '0'], 'page')->int()->min(1)->value();   // throws OutOfRangeInputException
Input::from([], 'remember')->bool()->value();                   // false — unchecked checkbox
Input::from(['q' => null], 'q')->str()->value();                // throws InvalidInputException — present null is present
```

[↑ Back to top](#accessor)

---

## `orNull` / `orElse`

The lenient terminals: the coerced value, or — on absence or any failure — `null` / the given default. They never throw for input; the failures (including a `required()` Missing) are discarded.

```php
Input::from($_GET, 'q')->str()->orNull();                       // 'abc' | null
Input::from($_GET, 'page')->int()->min(1)->orElse(1);           // 3 | 1
Input::from([], 'remember')->bool()->orElse(true);              // false — absence IS the bool false,
                                                                //         not a failure to fall back from
```

[↑ Back to top](#accessor)

---

## `get`

The collect terminal — only on accessors born from `Input::validate()->field()` (elsewhere it is a `LogicException`). Records every failure into the validator and returns the **best-effort value**: the coerced value even when a verifier failed, `null` for a skipped or uncoercible field, `false` for an absent bare bool. An absent key fails only when the chain says `required()`; otherwise the field is skipped and its value is null. See [`Validator`](validator.md).

```php
$form = Input::validate(['age' => '150']);
$age  = $form->field('age')->int()->between(18, 120)->get();   // 150 — coerced; the failure is recorded
$form->fails();                                                // true
```

[↑ Back to top](#accessor)

---

## Chain grammar — `LogicException`

Misusing the chain itself is a programmer error, never an input failure: a terminal or verifier before a coercer, or a second coercer, throws `LogicException`. There is no implicit `str()` — the explicit raw read is `->str()`.

```php
Input::from($_GET, 'q')->value();          // LogicException — no coercer opened the chain
Input::from($_GET, 'q')->min(1);           // LogicException — verifier before the coercer
Input::from($_GET, 'q')->int()->str();     // LogicException — exactly one coercer opens a chain
Input::from($_GET, 'q')->str()->get();     // LogicException — get() needs a validator
```

[↑ Back to top](#accessor)
