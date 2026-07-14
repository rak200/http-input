# Reference

Per-class API reference with runnable examples. For installation and an overview, see the [top-level README](../README.md).

| Class | Doc | What it covers |
| ----- | --- | -------------- |
| `Input` | [input.md](input.md) | The static entry facade — `from`, `validate`, and the superglobal shortcuts |
| `Rule` | [rule.md](rule.md) | The free-standing constraint chain — coercers, verifiers, flags |
| `Accessor` | [accessor.md](accessor.md) | A `Rule` bound to `(source, key)`, plus the terminals |
| `Validator`, `Gate` | [validator.md](validator.md) | Collect mode — the shared error bag and the form-level cross-field gate |
| `Constraint`, `Violation`, `Outcome` | [contracts.md](contracts.md) | The extension surface — custom constraints and the application result |
| `Exception\*` | [exceptions.md](exceptions.md) | The `InputException` hierarchy and failure paths |

## Conventions used in these docs

- Output is shown in trailing `// …` comments next to each call.
- A *failure* is an `InputException` that has not been thrown yet; the terminal decides its fate.
- All snippets assume `use Rak200\HttpInput\Input;` (plus `Rule`, `Validator`, … where shown).
