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
| 1 | Medium | `src/Accessor.php`, RFC 0013 | Thrown `InputException`s carry no key context — a multi-read `catch` cannot tell which parameter failed |

> Former items 1 (dot-path `Arr::has()` presence checks) and 2 (duplicated
> clamp logic) were retired by milestone 6: the 0.1.x static API that carried
> both defects no longer exists — the accessor has used literal-key lookups
> since milestone 3, and clamping was replaced by rejecting `min`/`max`.

---

### 1 — Bind the failing key to thrown `InputException`s (Medium)

**Files:** `src/Accessor.php` (`value()`), `src/Exception/InputException.php`;
needs an RFC 0013 amendment in the devr repository.

**Problem.** RFC 0013 fixes failure messages as field-agnostic predicates
(`'must be at least 1'`) so the collect bag can key them by field —
`messages()` → `{field: [message]}`. The strict terminal `value()` throws that
same field-less exception, so a `catch (InputException)` wrapping **several**
reads (e.g. a controller turning failures into a 400 response) cannot tell
*which* parameter failed:

```php
try {
    $page = Input::from($_GET, 'page')->int()->min(1)->value();
    $size = Input::from($_GET, 'size')->int()->max(100)->value();
} catch (InputException $e) {
    // $e->getMessage() is 'must be at least 1' — but for which key?
}
```

**Proposed fix.** Bind the key at the terminal: a nullable `key` accessor on
`InputException`, set by `value()` (and by the validator when recording) at the
moment the failure is materialised. `getMessage()` stays field-less, so the
`messages()` view and the RFC's message vocabulary are untouched. Rejected
alternative: prefixing the key into the message — it would break the field-less
`messages()` contract and duplicate the bag's keys.

> Milestone 5 already shipped half the mechanism: `InputException::at()`/`nest()`
> carry the *relative* path of nested failures (`tags.0`). This item is the
> remaining top-level binding — the field key itself — pending the RFC amendment.

**Acceptance criteria.**
- After a `value()` throw, the caught `InputException` exposes the failing key.
- `getMessage()` output is unchanged; `messages()` output is unchanged.
- RFC 0013 is amended in devr recording the key-binding decision.
