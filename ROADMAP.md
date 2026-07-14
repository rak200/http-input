# Roadmap

Tracked upcoming work for `rak200/http-input`: the `0.2.0` redesign and
self-contained follow-up items surfaced by release reviews.

## 0.2.0 — strict reads, verification, validation (RFCs 0013/0014)

The library's founding premise — *no method throws; a missing or uncoercible value
becomes the caller's default* — is replaced by a **constraint chain with three
terminals**: `value()` (throw), `orNull()`/`orElse()` (lenient), `get()` (collect).
Reading, verification, and validation become one mechanism. The design is fixed by two
accepted proposals in the [devr repository](https://github.com/rak200/devr), under
`docs/proposals/`: **RFC 0013** (strict reads + unified verification/validation) and
**RFC 0014** (structured JSON / schema validation). This is the pre-1.0 breaking
change; the old `Input` API is removed.

Milestones land in order, each self-contained with its tests — the RFC behaviour
tables (numeric assert/coerce, bool vocabulary) translate 1:1 into data providers.

1. **Foundation** — exception hierarchy (`InputException` → `MissingInputException` /
   `InvalidInputException`, per-constraint subtypes such as
   `OutOfRangeInputException`), `Violation`, and the `Constraint` interface.
2. **`Rule` — scalar leaves** — the free-standing chain: coercers `str` / `int` /
   `float` / `num` / `bool` with assert-by-default + `coerce()` (lossless, one step
   past `Filter::to*`; bare `bool` accepts only `on`/absent and `true`/`false`);
   verifiers `required`, `min`/`max`/`between`, `minLen`/`maxLen`/`lenBetween`,
   `sameAs`, `email`/`url`/`pattern`, `in`; custom constraints via `rule()` /
   `satisfy()`.
3. **`Accessor` + terminals** — a `Rule` bound to `(source, key)`; `value()` /
   `orNull()` / `orElse()`; `Input::from()`. Uses literal-key lookups
   (`Arr::hasKey` / `Arr::getKeyOrNull`) — absorbs follow-up item 1. A terminal
   reached without a coercer is a `LogicException`.
4. **`Validator`** — `Input::validate()`; collect-mode `get()`;
   `requires()->assert()`; `fails()` / `errors()` / `messages()` / `values()`.
5. **Domain coercers + flags** — temporal masks (`date`/`time`/`datetime`/`timestamp`
   via `Dt::parseOrNull`), `enum()` (backed-type branching), `listOf(Rule)` with
   index-keyed errors, and the `nullable()` flag.
6. **Superglobal shortcuts + cleanup** — accessor-returning `get`/`post`/`cookie`/
   `server`/`env`/`request`; the old static API is removed; README and docs rewritten
   around the chain.
7. **JSON schema (RFC 0014)** — `Schema::object` (unknown keys denied by default) and
   `Schema::listOf`; `Result` (`fails`/`errors`/`messages`/`values` plus `valid()`,
   the fail-fast terminal); path-keyed errors (`items.0.qty`); `Input::json()` over
   `Json::decode` (malformed body → `JsonException` → 400, distinct from schema
   errors).
8. **Release 0.2.0** — changelog, tag, and RFCs 0013/0014 marked `Implemented` in
   devr.

## Follow-up items

Each item below is self-contained and meant to be resolved **independently** — its
own commit / PR — with its own acceptance criteria. Items are ordered by priority,
not by the order in which they must be done. Delivered items are pruned from here and
recorded in `CHANGELOG.md`.

### Status overview

| # | Priority | Area | Item |
|---|----------|------|------|
| 1 | High | `src/Input.php` | Presence checked via dot-path `Arr::has()` but value read literally |
| 2 | Low | `src/Input.php` | `float()` duplicates the clamp logic `int()` isolates in `clampInt()` |

---

### 1 — Use literal-key lookups instead of dot-path `Arr::has()` (High)

**Files:** `src/Input.php` — every accessor: `str` (`:38`), `int` (`:59`),
`float` (`:78`), `bool` (`:99`), `array` (`:115`), `has` (`:127`).

**Problem.** Presence is checked with `Arr::has()`, which interprets `.` as a
dot-path (`'a.b'` → `$source['a']['b']`), but the value is then read with the
**literal** `$source[$key]`. `utils` deliberately exposes a literal-key variant
(`Arr::hasKey()` / `Arr::getKeyOrNull()`); the accessors use the wrong one, so
any key containing a `.` is mishandled.

**Evidence (runtime).**
- Literal dotted key, present → value silently dropped:
  - `Input::has(['a.b' => 5], 'a.b')` → `false` (docblock promises `true`).
  - `Input::int(['a.b' => 5], 'a.b', 0)` → `0` (drops the present `5`).
  - `Input::array(['t.x' => [1, 2]], 't.x')` → `null`.
- Nested structure, dotted key → `has()` is `true` but the literal read emits
  `Undefined array key "a.b"` (a warning that would trip
  `failOnWarning="true"` in `phpunit.xml`).

This contradicts the documented literal-key semantics ("Returns true if `$key`
is present in `$source`") and the README's "read from any source array" — a
JSON body such as `{"filter.status": "active"}` is a realistic trigger. The
defect is independent of the `Filter::toStr()` fix shipped in 0.1.1.

**Proposed fix.** Guard with `Arr::hasKey($source, $key)` and read with
`Arr::getKeyOrNull($source, $key)` (which also removes the double lookup and the
per-call `Str::split` cost). Add regression tests for keys containing `.`.

**Acceptance criteria.**
- `Input::has(['a.b' => 5], 'a.b')` is `true`; `Input::int(['a.b' => 5], 'a.b')`
  is `5`; `Input::array(['t.x' => [1, 2]], 't.x')` is `[1, 2]`.
- No `Undefined array key` warning for any input.
- New tests cover literal dotted keys.

> **0.2.0 note:** absorbed into milestone 3 — the new `Accessor` must use literal-key
> lookups from day one. Fix it here only if a `0.1.x` patch ships before the redesign.

---

### 2 — De-duplicate the clamp logic (Low)

**Files:** `src/Input.php:82-88` (inline clamp in `float()`) vs
`src/Input.php:185-196` (`clampInt()` helper used by `int()`).

**Problem.** `int()` factors its min/max clamp into a private `clampInt()`
helper, while `float()` inlines the identical logic. Two copies of the same
"null passes through, then clamp low, then clamp high" rule can drift apart and
add avoidable noise.

**Proposed fix.** Extract a shared clamp (e.g. a `clampFloat()` sibling, or a
single generic helper over `int|float`) and have both `int()` and `float()`
route through it. Behaviour-preserving refactor — no API change.

**Acceptance criteria.**
- `float()` no longer inlines the min/max comparisons.
- Existing tests (`testIntClampsToBounds`, `testFloatClampsToBounds`) stay green
  with no changes.

> **0.2.0 note:** superseded by milestone 2 — the redesign removes clamping entirely
> (`min`/`max` become rejecting verifiers). Resolve only if a `0.1.x` patch needs it.
