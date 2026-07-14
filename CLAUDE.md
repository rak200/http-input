# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

The **cross-library rak200 PHP conventions** (baseline & tooling, dev dependencies, CI, code style, naming, `use function` inventory, first-class callables, correctness-over-efficiency, safe defaults, testing, versioning, README badges) are shared and imported below. This file keeps only what is specific to **http-input**.

@~/.claude/rak200-php-conventions.md

## Project Overview

**rak200/http-input** is a PHP 8.4+ library for strict, typed reading and validation of HTTP request data (`$_GET`, `$_POST`, cookies, server, env). Reads flow through a **constraint chain** (RFC 0013 in the devr repository): exactly one coercer fixes the value's type, verifiers check it, and a terminal decides every failure's fate — `value()` throws, `orNull()`/`orElse()` fall back, `get()` collects into a validator. Reading, verification, and validation are one mechanism; coercion delegates to `Rak200\Utils` (`Filter`, `Dt`, `Enum`) — the request-layer companion that keeps `utils` pure.

**Deliberate deviation from the shared "no runtime Composer dependencies" rule:** http-input requires **`rak200/utils` (`^4.0`)** at runtime — coercion is delegated to its `Filter` (`toStr` / `toInt` / `toFloat` / `toBool`) and key handling to `Arr` (the prefer-lib-over-native rule applied across libraries). utils is currently consumed through a `"type": "path"` repository entry pointing at the sibling `../utils` checkout — a local-development arrangement; consumers need a `"type": "vcs"` entry per repository (the pattern caster's README documents) until both libraries land on Packagist.

## Structure

```
input/
├── docs/                  # per-class reference pages (see docs/README.md index)
├── src/
│   ├── Input.php          # static entry facade: from(), validate(), superglobal shortcuts
│   ├── Rule.php           # the free-standing chain: coercers, verifiers, flags
│   ├── Accessor.php       # a Rule bound to (source, key) + the terminals
│   ├── Validator.php      # collect mode: the shared error bag
│   ├── Gate.php           # form-level cross-field gate (Validator::requires())
│   ├── Schema.php         # JSON tree combinators: object() / listOf() (RFC 0014)
│   ├── Result.php         # schema validation outcome: path-keyed bag + valid()
│   ├── Outcome.php        # result of applying a Rule to one value
│   ├── Constraint.php     # verifier contract for custom rules
│   ├── Violation.php      # field-agnostic failure a Constraint returns
│   └── Exception/         # InputException hierarchy (Missing/Invalid + per-constraint)
└── tests/                 # mirrors src/ (Rule split per coercer, caster-style)
    └── Fixture/           # test enums
```

Production classes live under `Rak200\HttpInput\` (PSR-4 from `src/`); test classes live under `Rak200\HttpInput\Tests\` (PSR-4 from `tests/`, dev-only).

## Conventions (http-input-specific)

The general PHP conventions live in the imported shared file above. What follows is specific to this library:

- **The chain grammar is fixed.** Exactly one coercer opens a chain; verifiers follow; a terminal ends it. Misusing the grammar (terminal or verifier before a coercer, a second coercer, `get()` outside collect mode, a pure enum without `byName`) is a `LogicException` — a programmer error, never an input failure. There is no implicit `str()`.
- **Failures are typed and deferred.** A failure is an `Exception\InputException` not yet thrown; the terminal decides its fate. `Missing` (absent) vs `Invalid` (present but failed) is a deliberate, catchable distinction, with per-constraint subtypes under `Invalid`. Messages are field-agnostic predicates (`'must be at least 1'`) so the collect bag can key them by field; nested failures carry a relative path (`at()`/`nest()`, e.g. `tags.0`).
- **Assert by default, coerce opt-in.** A bare scalar coercer accepts only a value that presents as the type — text format on the flat bag (`apply(..., typed: false)`), decoded PHP type in a JSON tree (`typed: true`). `coerce()` admits any lossless representation (one step past `Filter::to*`); the domain coercers (temporal, enum) always coerce from their carrier. Bare `bool` accepts only `on`/absent and `true`/`false` — absence is a legitimate `false` for it.
- **Immutability everywhere except the bag.** `Rule` and `Accessor` return new instances from every chain call (rules are reusable values); only `Validator` is stateful — it *is* the error bag.
- **Pure core, thin shortcuts.** Everything reads the array handed to it; only the superglobal shortcuts (`get`/`post`/`cookie`/`server`/`env`/`request`) touch a superglobal, and they return an *accessor*, never a pre-terminated value. Keys are literal, never dot-paths.
- **No coercion logic of its own.** Type-coercion rules belong to utils (`Filter`, `Dt::parseOrNull`, `Enum`); this library adds orchestration. If a coercion rule is wrong, fix it in utils, not here — the one sanctioned extension is the documented "one step past `Filter::toInt`" for whole decimal text.
- **Per-class docs.** Every new or changed public method must be reflected in `docs/` (see the `docs/README.md` index for the page map), following the layout in the shared conventions.

## Testing

General testing conventions are in the shared file. http-input specifics:

- PHPUnit is configured via `phpunit.xml` with a single `Unit` suite.
- The RFC 0013/0014 behaviour tables (numeric assert/coerce, bool vocabulary) are data providers 1:1; `Rule` tests are split per coercer (caster-style: `RuleIntTest`, `RuleBoolTest`, …).
- Everything except the superglobal shortcuts is tested pure — literal source arrays in, values out. Shortcut tests mutate `$_GET` / `$_POST` / `$_REQUEST` / `$_COOKIE` / `$_SERVER` / `$_ENV` and carry `#[BackupGlobals(true)]` so the mutation never leaks across tests.
- Test enums live in `tests/Fixture/`.

## Versioning & releases

SemVer policy and the release checklist live in the shared conventions. http-input deltas:

- Not on Packagist yet — consumers resolve from git (see the Project Overview note on the `path` repository).
- No CI workflow yet — `.github/workflows/ci.yml` from the shared conventions is still pending here; the local gate (`composer test`, `composer phpstan`, `composer cs-check`) is the release gate until it exists.

## Roadmap

Pending work is tracked in the repo-root [ROADMAP.md](ROADMAP.md) (public, linked from the README): the **0.2.0 redesign** — strict reads + unified verification/validation per RFCs 0013/0014 in the devr repository — plus self-contained follow-up items. Items are **pruned** on delivery (shared release checklist); `CHANGELOG.md` is the historical record.
