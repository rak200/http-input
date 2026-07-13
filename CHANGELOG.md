# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

[0.1.1]: https://github.com/rak200/http-input/compare/0.1.0...0.1.1
[0.1.0]: https://github.com/rak200/http-input/releases/tag/0.1.0
