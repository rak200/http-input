# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.2] - 2026-07-15

Internal quality and tooling only — no public API or behaviour change.

### Changed

- `Result::messages()` and `Validator::messages()` drop their `@var` type-suppressions: the nested `Arr::map` that stringifies each failure list is extracted into a private `failureMessages()` helper, passed as a first-class callable. A named callable's declared `list<string>` return survives PHPStan's template inference where an inline closure's is generalised away — so the `array<string, list<string>>` return type is now proven end-to-end at PHPStan level max, with no suppression. Public signatures and runtime behaviour are unchanged.
- CI: bumped `codecov/codecov-action` to v7 (Node 24).

## [0.2.1] - 2026-07-15

### Added

- Mutation testing via [Infection](https://infection.github.io) (`composer infection`, configured in `infection.json5`), wired into CI on the floor job. The suite was hardened against every escaped mutant: chain-flag immutability (`required()`/`nullable()`), single-case enums, overflowing numeric text under `int()->coerce()`, a JSON list where a schema object is expected, and `Input::json()` now tested end-to-end through a `php://` stream-wrapper mock (readable and unreadable bodies).
- README mutation-testing badge (`Infection — min MSI 100%`), mirroring `infection.json5`.

### Changed

- `Schema` object nodes now propagate the absent-leaf `Outcome` value into the clean tree instead of discarding it (behaviour-identical today; honours the `applyAbsent()` contract).
- `Rule` membership messages no longer cast scalars redundantly before `Str::join` (dead code surfaced by mutation testing).

## [0.2.0] - 2026-07-14

The pre-1.0 redesign fixed by RFCs 0013/0014 (devr repository): reading, verification, and validation become one mechanism — a **constraint chain** in which exactly one coercer fixes the value's type, verifiers check it, and a terminal decides every failure's fate.

### Added

- **`Rule`** — the free-standing, immutable constraint chain (rules are reusable values):
  - Coercers: `str`, `int`, `float`, `num`, `bool`, `date` / `time` / `datetime` / `timestamp` (mask-based, via `Dt::parseOrNull`), `enum` (backed value by default; `byName: true` for name lookup — mandatory for pure enums), `listOf` (homogeneous list, element failures keyed by index).
  - Assert by default: a bare scalar coercer accepts only a value already presenting as the type (`'42'` is an int, `'42.0'` is not); `coerce()` opts into any lossless conversion. A bare `bool` accepts only `on`/absent and `true`/`false` — absence is a legitimate `false`.
  - Verifiers: `min` / `max` / `between` (numbers and dates — out-of-range values are **rejected**, never clamped), `minLen` / `maxLen` / `lenBetween` (characters for strings, count for lists), `email` / `url` / `pattern`, `in`, `sameAs`, and `requires` (gates on already-read dependencies, so a missing dependency never double-reports).
  - Flags: `required()` (absence is a failure), `nullable()` (explicit null accepted, verifiers short-circuited).
  - Custom constraints: `satisfy(callable, message)` one-offs and reusable `rule(Constraint)` objects (`Constraint::check(): ?Violation`).
- **`Accessor` + terminals** — a `Rule` bound to `(source, key)` by `Input::from()`; keys are literal, never dot-paths. Three endings: `value()` throws the first failure, `orNull()` / `orElse($fallback)` fall back, `get()` records into a validator.
- **Collect mode** — `Input::validate($source)` returns a `Validator` (`field()`, `fails()`, `errors()`, `messages()`, `values()`) reporting every failure at once; `requires(...)->assert(...)` adds form-level cross-field checks (`Gate`).
- **JSON schema validation** — `Schema::object()` (unknown keys rejected by default; `allowUnknownKeys()` opts out) and `Schema::listOf()` compose the same rules over decoded trees; leaves assert the **decoded PHP type**, with `coerce()` re-enabling lossless conversion. `validate()` returns a `Result` — the path-keyed bag (`items.0.qty`) plus `valid()`, the fail-fast terminal. `Input::json()` reads `php://input`; a malformed body throws `JsonException`, deliberately distinct from schema errors.
- **Typed, deferred failures** — `InputException` → `MissingInputException` (absent) / `InvalidInputException` (present but failed), with per-constraint subtypes (`OutOfRangeInputException`, `LengthInputException`, `FormatInputException`, `MismatchInputException`, `MembershipInputException`). Messages are field-agnostic predicates; failures raised inside nested structures carry a relative path via `at()` / `nest()`. Misusing the chain itself (a terminal or verifier before a coercer, a second coercer, `get()` outside collect mode) is a `LogicException` — a programmer error, never an input failure.
- PHP-CS-Fixer (`@PhpCsFixer` preset + risky) with `composer cs-check` / `cs-fix`; the bulk reformat is recorded in `.git-blame-ignore-revs`.
- CI (GitHub Actions): a PHP 8.4/8.5 matrix running `composer validate`, PHP-CS-Fixer (floor job only), PHPStan, and PHPUnit with coverage uploaded to Codecov; the workflow checks out the sibling `rak200/utils` so the `path` repository entry resolves. README badges (CI, coverage, latest tag, PHP, PHPStan, code style, license, SemVer, Keep a Changelog).

### Changed

- **Breaking:** the superglobal shortcuts (`get`, `post`, `cookie`, `server`, `env`, `request`) now return an `Accessor` to be terminated explicitly — no longer a `?string` with a `$default` parameter.
- Documentation rewritten around the chain: per-class reference pages under `docs/` (see the `docs/README.md` index).

### Removed

- **Breaking:** the 0.1.x static API — `Input::str()` / `int()` / `float()` / `bool()` / `array()` / `has()` / `all()`, their `$default` parameters, and `int` / `float` **clamping** via `min` / `max`. The founding *"no method throws; a missing or uncoercible value becomes the caller's default"* premise goes with it: lenient reads are now explicit terminals (`->orElse($default)`, `->orNull()`), and ranges are verified and rejected instead of clamped.

## [0.1.1] - 2026-07-13

### Changed

- Raised the `rak200/utils` requirement from `^1.10` to `^4.0`. The string coercer was renamed `Filter::toString` → `Filter::toStr` in utils `2.0`, so the reader now targets the current utils API.

### Fixed

- `Input::str()` — and the superglobal shortcuts `get`/`post`/`request`/`cookie`/`server`/`env` that delegate to it — called the non-existent `Filter::toString()`, raising a fatal `Error` on every string read. They now call `Filter::toStr()`.

## [0.1.0] - 2026-05-30

### Added

- Initial release.
- **`Input`** — typed, safe reading of HTTP request data, built on `rak200/utils`'s `Filter`.
  - Pure core over a caller-supplied source array: `str`, `int` (with optional `min`/`max` clamping), `float` (with optional `min`/`max`), `bool` (HTML-form semantics via `Filter::toBool`), `array`, `has`, `all`. A missing key or uncoercible value returns the supplied default — no exceptions.
  - Convenience shortcuts that read a string from a superglobal: `get` (`$_GET`), `post` (`$_POST`), `request` (`$_REQUEST`), `cookie` (`$_COOKIE`), `server` (`$_SERVER`), `env` (`$_ENV`). Typed reads from superglobals use the core directly, e.g. `Input::int($_GET, 'page', 1)`.

[0.2.2]: https://github.com/rak200/http-input/compare/0.2.1...0.2.2
[0.2.1]: https://github.com/rak200/http-input/compare/0.2.0...0.2.1
[0.2.0]: https://github.com/rak200/http-input/compare/0.1.1...0.2.0
[0.1.1]: https://github.com/rak200/http-input/compare/0.1.0...0.1.1
[0.1.0]: https://github.com/rak200/http-input/releases/tag/0.1.0
