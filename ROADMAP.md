# Roadmap

Tracked upcoming work for `rak200/http-input`: self-contained follow-up items surfaced
by release reviews.

## Follow-up items

Each item below is self-contained and meant to be resolved **independently** — its
own commit / PR — with its own acceptance criteria. Items are ordered by priority,
not by the order in which they must be done. Delivered items are pruned from here and
recorded in `CHANGELOG.md`.

### Status overview

| # | Priority | Area | Item |
|---|----------|------|------|
| 1 | Medium | `src/Accessor.php`, RFC 0013 | Thrown `InputException`s carry no key context — a multi-read `catch` cannot tell which parameter failed |

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

> The 0.2.0 release already shipped half the mechanism: `InputException::at()`/`nest()`
> carry the *relative* path of nested failures (`tags.0`). This item is the
> remaining top-level binding — the field key itself — pending the RFC amendment.

**Acceptance criteria.**
- After a `value()` throw, the caught `InputException` exposes the failing key.
- `getMessage()` output is unchanged; `messages()` output is unchanged.
- RFC 0013 is amended in devr recording the key-binding decision.
