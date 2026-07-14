# Rule

[← Reference](README.md)

A free-standing constraint chain: exactly one coercer, then verifiers — with no source and no terminal. Built fluently and used wherever a chain is a *value* (the element rule in `listOf`, schema leaves); immutable, so one rule is reusable across fields. Applying it to a value yields an [`Outcome`](contracts.md#outcome).

```php
use Rak200\HttpInput\Rule;
```

## Contents

- [Coercers: `str` / `int` / `float` / `num` / `bool`](#coercers-str--int--float--num--bool)
- [`coerce` — assert vs coerce](#coerce--assert-vs-coerce)
- [`date` / `time` / `datetime` / `timestamp`](#date--time--datetime--timestamp)
- [`enum`](#enum)
- [`listOf`](#listof)
- [`required` / `nullable`](#required--nullable)
- [`min` / `max` / `between`](#min--max--between)
- [`minLen` / `maxLen` / `lenBetween`](#minlen--maxlen--lenbetween)
- [`email` / `url` / `pattern`](#email--url--pattern)
- [`in` / `sameAs` / `requires`](#in--sameas--requires)
- [`rule` / `satisfy`](#rule--satisfy)
- [`apply` / `applyAbsent`](#apply--applyabsent)

---

## Coercers: `str` / `int` / `float` / `num` / `bool`

Exactly one coercer opens a chain and fixes the value's type. A **bare** coercer *asserts*: the value must already present as the declared type — by its **text format** on the flat request bag, by its **decoded PHP type** in a JSON tree (see [`apply`](#apply--applyabsent)).

| Input (text) | `int()` | `float()` | `num()` |
| ------------ | ------- | --------- | ------- |
| `'42'` | `42` | ✗ | `42` (int) |
| `'42.0'` | ✗ | `42.0` | `42.0` (float) |
| `'42.5'` | ✗ | `42.5` | `42.5` (float) |

```php
Rule::str()->apply('Ada')->value;     // 'Ada'
Rule::int()->apply('42')->value;      // 42
Rule::int()->apply('42.5')->failed(); // true — 'must be an integer'
Rule::num()->apply('42.0')->value;    // 42.0 (presentation preserved)
```

A bare `bool()` accepts exactly the two standard pairs — the HTML checkbox pair (`'on'` → `true`, **absent** → `false`) and the API text pair (`'true'`/`'false'`). Everything else `Filter::toBool` understands (`'1'`/`'0'`, `'yes'`/`'no'`, `'off'`, `''`, case variants) is [`coerce()`](#coerce--assert-vs-coerce)-only.

```php
Rule::bool()->apply('on')->value;      // true
Rule::bool()->apply('1')->failed();    // true — coerce()-only vocabulary
Rule::bool()->applyAbsent()->value;    // false — an unchecked checkbox submits nothing
```

[↑ Back to top](#rule)

---

## `coerce` — assert vs coerce

`coerce()` opts into leniency: besides the bare presentation, any *other* representation that maps to the type **without loss** is accepted — one step past `Filter::to*`. The whole decimal `'42.0'` narrows to int `42`; the fraction `'42.5'` never does.

| Input | `int()->coerce()` | `float()->coerce()` | `num()->coerce()` |
| ----- | ----------------- | ------------------- | ----------------- |
| `'42'` | `42` | `42.0` | `42` (int) |
| `'42.0'` | `42` | `42.0` | `42.0` (float) |
| `'42.5'` | ✗ | `42.5` | `42.5` (float) |

```php
Rule::int()->coerce()->apply('42.0')->value;   // 42
Rule::int()->coerce()->apply(' 42 ')->value;   // 42   (trimmed)
Rule::str()->coerce()->apply(42)->value;       // '42'
Rule::bool()->coerce()->apply('yes')->value;   // true (full Filter vocabulary)
```

The flag is inert on the domain coercers below — they have no native carrier and always coerce.

[↑ Back to top](#rule)

---

## `date` / `time` / `datetime` / `timestamp`

Temporal masks parse their **string carrier** via `Dt::parseOrNull` into a `DateTimeImmutable`; `timestamp()` yields an int from any lossless integer representation (epoch seconds). JSON has no temporal type, so bare/`coerce()` makes no difference here.

```php
Rule::date()->apply('2026-01-15')->value;               // DateTimeImmutable (mask 'Y-m-d')
Rule::date('d/m/Y')->apply('15/01/2026')->value;        // DateTimeImmutable
Rule::time('H:i')->apply('09:30')->value;               // DateTimeImmutable (time part)
Rule::datetime()->apply('2026-01-15 09:30:00')->value;  // DateTimeImmutable ('Y-m-d H:i:s')
Rule::timestamp()->apply('1736899200')->value;          // 1736899200

Rule::date()->apply('15/01/2026')->failures[0]->getMessage();   // 'must be a valid date (Y-m-d)'
```

Temporal values are ordered — the [range verifiers](#min--max--between) compare them:

```php
Rule::date()->min(new DateTimeImmutable('2026-01-01'))->apply('2025-12-31')->failed();   // true
```

[↑ Back to top](#rule)

---

## `enum`

Matches the raw scalar against an enum, yielding the **case**. Default: match by backed value — the scalar is coerced to the backing type first, so `'2'` matches an int-backed case. `byName: true` opts into matching by case name instead (the only mode a pure enum supports).

```php
enum Priority: int { case Low = 1; case Mid = 2; case High = 3; }

Rule::enum(Priority::class)->apply('2')->value;                  // Priority::Mid
Rule::enum(Priority::class)->apply('9')->failures[0]->getMessage();   // 'must be one of 1, 2, 3'
Rule::enum(Priority::class, byName: true)->apply('Mid')->value;  // Priority::Mid

Rule::enum(PureEnum::class);   // LogicException — a pure enum matches by name only
```

[↑ Back to top](#rule)

---

## `listOf`

The value must be a **list** (a checkbox array, a multi-select); each element runs the full element rule — coercion and verifiers, index-keyed failures relative to the field ([`at()`](exceptions.md#at--nest)). Count and presence are ordinary verifiers on top; an unchecked checkbox array is **absent**, not `[]`. With no element rule, elements pass through as mixed.

```php
Rule::listOf(Rule::int())->apply(['1', '2'])->value;          // [1, 2]
Rule::listOf(Rule::str()->in(['s', 'm']))->apply(['s', 'x'])
    ->failures[0]->at();                                      // '1'
Rule::listOf(Rule::str())->apply('abc')->failed();            // true — 'must be a list'
Rule::listOf(Rule::str())->minLen(1)->apply([])
    ->failures[0]->getMessage();                              // 'must have at least 1 item'
```

[↑ Back to top](#rule)

---

## `required` / `nullable`

The presence vocabulary — two independent flags. `required()` rejects an **absent** key (a `MissingInputException` instead of a skip or the bare-bool false). `nullable()` accepts a **present-but-null** value: an explicit null short-circuits the chain successfully, yielding null — only a decoded JSON tree carries a real null; on the flat bag it is inert.

```php
Rule::str()->required()->applyAbsent()->failures[0]->getMessage();   // 'is required'
Rule::int()->nullable()->apply(null, typed: true)->value;            // null (no failure)
Rule::str()->required()->nullable();   // the key must exist, may be null
```

[↑ Back to top](#rule)

---

## `min` / `max` / `between`

The ordered-range verifiers, over any ordered value the coercer produced — numbers, temporal values. Bounds are inclusive. Their failure is an `OutOfRangeInputException`.

```php
Rule::int()->min(1)->apply('0')->failures[0]->getMessage();       // 'must be at least 1'
Rule::int()->between(1, 100)->apply('100')->failed();             // false (inclusive)
Rule::float()->max(9.5)->apply('10.0')->failed();                 // true
```

[↑ Back to top](#rule)

---

## `minLen` / `maxLen` / `lenBetween`

The length verifiers — **characters** for strings (multibyte-safe, `Str::len`), **element count** for lists. Their failure is a `LengthInputException`.

```php
Rule::str()->minLen(4)->apply('ação')->failed();                  // false — 4 characters, 6 bytes
Rule::str()->minLen(3)->apply('ab')->failures[0]->getMessage();   // 'must be at least 3 characters'
Rule::listOf(Rule::str())->maxLen(1)->apply(['a', 'b'])
    ->failures[0]->getMessage();                                  // 'must have at most 1 item'
```

[↑ Back to top](#rule)

---

## `email` / `url` / `pattern`

The format verifiers — `FILTER_VALIDATE_EMAIL`, `Url::is`, `Regex::matches`. Their failure is a `FormatInputException`.

```php
Rule::str()->email()->apply('ada@example.com')->failed();     // false
Rule::str()->url()->apply('https://example.com')->failed();   // false
Rule::str()->pattern('/^[a-z0-9-]+$/')->apply('my-slug')->failed();   // false
Rule::str()->email()->apply('nope')->failures[0]->getMessage();       // 'must be a valid e-mail'
```

[↑ Back to top](#rule)

---

## `in` / `sameAs` / `requires`

`in` checks membership in an ad-hoc set (strict comparison) — membership in an enum is [`enum`](#enum)'s job. `sameAs` is the cross-field match against an **already-read value**; `requires` gates the rest of the chain on such dependencies — when any is null, every later step is skipped, so no verifier sees a null dependency and the field gains no spurious error (the missing dependency reports its own).

```php
Rule::str()->in(['s', 'm', 'l'])->apply('x')->failures[0]->getMessage();   // 'must be one of s, m, l'

$pw = 'secret';
Rule::str()->requires($pw)->sameAs($pw)->apply('secret')->failed();   // false
Rule::str()->requires(null)->sameAs(null)->apply('anything')->failed();   // false — gated, skipped
```

[↑ Back to top](#rule)

---

## `rule` / `satisfy`

Custom constraints. `rule` appends a reusable [`Constraint`](contracts.md#constraint); `satisfy` is sugar for a one-off closure — message and, optionally, the `InputException` subtype to raise.

```php
Rule::int()->satisfy(fn ($v) => $v % 3 === 0, 'must be divisible by 3');
Rule::int()->rule(new DivisibleBy(3));
Rule::int()->satisfy(fn ($v) => $v <= 999, 'must fit the quota', OutOfRangeInputException::class);
```

[↑ Back to top](#rule)

---

## `apply` / `applyAbsent`

`apply($value, $typed = false)` runs the chain over a present value and returns an [`Outcome`](contracts.md#outcome) — the coerced value plus every failure, none thrown. `$typed` selects what a bare coercer asserts: `false` = the flat bag (text formats), `true` = a decoded JSON tree (PHP types). Coercion failure short-circuits (one failure, no verifier runs); verifier failures are all collected in declaration order.

```php
$outcome = Rule::int()->min(1)->apply('0');
$outcome->value;      // 0     (coerced fine; the range failed)
$outcome->failed();   // true
$outcome->failures;   // [OutOfRangeInputException('must be at least 1')]

Rule::int()->apply('42', typed: true)->failed();   // true — a JSON string is not an int
```

`applyAbsent($typed = false)` answers for an absent key where the chain has an opinion: `required()` → the Missing failure, bare `bool` → `false`; null otherwise — the terminal decides. The bare-`bool` `false` fires only untyped: the unchecked-checkbox convention is HTML's, so in a decoded JSON tree (`typed: true`) an absent bool follows the normal presence rules.

[↑ Back to top](#rule)
