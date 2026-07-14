# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

The **cross-library rak200 PHP conventions** (baseline & tooling, dev dependencies, CI, code style, naming, `use function` inventory, first-class callables, correctness-over-efficiency, safe defaults, testing, versioning, README badges) are shared and imported below. This file keeps only what is specific to **http-input**.

@~/.claude/rak200-php-conventions.md

## Project Overview

**rak200/http-input** is a PHP 8.4+ library for typed, safe reading of HTTP request data (`$_GET`, `$_POST`, cookies, server, env). A single `final` class, `Input`, reads a key from a caller-supplied source array, coerces it via `Rak200\Utils\Filter`, and returns a caller-supplied default when the key is missing or the value cannot be represented — the request-layer companion that keeps `utils` pure.

**Deliberate deviation from the shared "no runtime Composer dependencies" rule:** http-input requires **`rak200/utils` (`^4.0`)** at runtime — coercion is delegated to its `Filter` (`toStr` / `toInt` / `toFloat` / `toBool`) and key handling to `Arr` (the prefer-lib-over-native rule applied across libraries). utils is currently consumed through a `"type": "path"` repository entry pointing at the sibling `../utils` checkout — a local-development arrangement; consumers need a `"type": "vcs"` entry per repository (the pattern caster's README documents) until both libraries land on Packagist.

## Structure

```
input/
├── docs/               # per-class reference pages (input.md + index)
├── src/
│   └── Input.php       # the single class — pure typed core + superglobal shortcuts
└── tests/
    └── InputTest.php
```

Production classes live under `Rak200\HttpInput\` (PSR-4 from `src/`); test classes live under `Rak200\HttpInput\Tests\` (PSR-4 from `tests/`, dev-only).

## Conventions (http-input-specific)

The general PHP conventions live in the imported shared file above. What follows is specific to this library:

- **Static-only.** `Input` is `final` with a `private` constructor and only `public static` methods — no instances, no state.
- **Pure core, thin shortcuts.** The typed accessors (`str`, `int`, `float`, `bool`, `array`, `has`, `all`) take the source array as their first argument — pure and directly testable, and they work on a superglobal passed in by the caller (`Input::int($_GET, 'page', 1)`). Only the shortcuts (`get`, `post`, `request`, `cookie`, `server`, `env`) touch superglobals themselves, each a one-line delegation to `str`.
- **No method throws (the 0.1.x contract).** A missing key or an uncoercible value returns the caller-supplied `$default` — no exceptions, no `isset()` ladders. The 0.2.0 redesign (see Roadmap) deliberately **replaces** this premise with a constraint chain; until it lands, all 0.1.x work preserves the contract.
- **No coercion logic of its own.** Type-coercion rules belong to `Filter` in utils; `Input` adds only the key-presence/default plumbing (plus the optional `int`/`float` `min`/`max` clamping in 0.1.x). If a coercion rule is wrong, fix it in utils, not here.
- **Per-class docs.** `docs/input.md` (+ the `docs/README.md` index) must reflect every new or changed public method, following the layout in the shared conventions.

## Testing

General testing conventions are in the shared file. http-input specifics:

- PHPUnit is configured via `phpunit.xml` with a single `Unit` suite.
- Core accessors are tested pure — literal source arrays in, values out.
- Superglobal shortcut tests mutate `$_GET` / `$_POST` / `$_REQUEST` / `$_COOKIE` / `$_SERVER` / `$_ENV` and carry `#[BackupGlobals(true)]` so the mutation never leaks across tests.

## Versioning & releases

SemVer policy and the release checklist live in the shared conventions. http-input deltas:

- Not on Packagist yet — consumers resolve from git (see the Project Overview note on the `path` repository).
- No CI workflow yet — `.github/workflows/ci.yml` from the shared conventions is still pending here; the local gate (`composer test`, `composer phpstan`, `composer cs-check`) is the release gate until it exists.

## Roadmap

Pending work is tracked in the repo-root [ROADMAP.md](ROADMAP.md) (public, linked from the README): the **0.2.0 redesign** — strict reads + unified verification/validation per RFCs 0013/0014 in the devr repository — plus self-contained follow-up items. Items are **pruned** on delivery (shared release checklist); `CHANGELOG.md` is the historical record.
