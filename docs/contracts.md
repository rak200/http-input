# Constraint, Violation & Outcome

[← Reference](README.md)

The extension surface: `Constraint` is the verifier contract custom rules implement, `Violation` the field-agnostic failure they return, and `Outcome` the result of applying a [`Rule`](rule.md) to one value.

```php
use Rak200\HttpInput\{Constraint, Violation, Outcome, Rule};
```

## Contents

- [`Constraint`](#constraint)
- [`Violation`](#violation)
- [`Outcome`](#outcome)

---

## `Constraint`

The verifier contract: `check(mixed $value): ?Violation` — null when the value passes, a `Violation` describing the failure otherwise. Implementations are **field-agnostic** (they see only the value), so one instance is reusable across fields. Plug one into a chain with `->rule()`; `->satisfy()` wraps a closure into an anonymous one.

```php
final class DivisibleBy implements Constraint
{
    public function __construct(private readonly int $divisor) {}

    public function check(mixed $value): ?Violation
    {
        return is_int($value) && $value % $this->divisor === 0
            ? null
            : new Violation("must be divisible by {$this->divisor}");
    }
}

Rule::int()->rule(new DivisibleBy(3))->apply('9')->failed();   // false
```

[↑ Back to top](#constraint-violation--outcome)

---

## `Violation`

The field-agnostic failure a constraint returns: the human message plus, optionally, the [`InputException`](exceptions.md) subtype the failure should surface as (default: `InvalidInputException`). The accessor binds it to the concrete `(source, key)` and materialises the exception the terminal throws, records, or discards.

```php
new Violation('must be even');                                        // → InvalidInputException
new Violation('must be positive', OutOfRangeInputException::class);   // → OutOfRangeInputException
```

[↑ Back to top](#constraint-violation--outcome)

---

## `Outcome`

The result of `Rule::apply()`: the coerced value plus every failure the chain produced, none thrown — the terminal decides their fate. When coercion itself fails the value is null and the single coercion failure is the only entry.

```php
$outcome = Rule::int()->min(1)->apply('0');

$outcome->value;      // 0      (coerced fine; the range failed)
$outcome->failures;   // [OutOfRangeInputException('must be at least 1')]
$outcome->failed();   // true
```

[↑ Back to top](#constraint-violation--outcome)
