# Roadmap

Tracked follow-up work for `rak200/http-input`, surfaced by the review of the
`0.1.0` release. Each item below is self-contained and meant to be resolved
**independently** — its own commit / PR — with its own acceptance criteria.

Items are ordered by priority, not by the order in which they must be done.
Delivered items are pruned from here and recorded in `CHANGELOG.md`.

## Status overview

| # | Priority | Area | Item |
|---|----------|------|------|
| 1 | High | `src/Input.php` | Presence checked via dot-path `Arr::has()` but value read literally |
| 2 | Low | `src/Input.php` | `float()` duplicates the clamp logic `int()` isolates in `clampInt()` |

---

## 1 — Use literal-key lookups instead of dot-path `Arr::has()` (High)

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

---

## 2 — De-duplicate the clamp logic (Low)

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
