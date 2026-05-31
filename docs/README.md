# Reference

Per-class API reference with runnable examples. For installation and an overview, see the [top-level README](../README.md).

| Class    | Doc                  | What it covers |
| -------- | -------------------- | -------------- |
| `Input`  | [input.md](input.md) | Typed reading of request data — pure core over a source array, plus superglobal shortcuts |

## Conventions used in these docs

- Output is shown in trailing `// …` comments next to each call.
- No method throws: a missing key or an uncoercible value returns the caller-supplied default.
- All snippets assume `use Rak200\HttpInput\Input;`.
