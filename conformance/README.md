# Conformance

Shared data every implementation must reproduce ([spec](../spec)). Compare values exactly; object keys in order.

| File | Check |
|---|---|
| `catalog.json` | Load `catalog`. It must be rejected when `valid` is `false`. When `valid` is `true`, the default event names must equal `event_names`, and each id in `pages` must resolve to its form, step and mode |
| `values.json` | Find the value of `from` in `context`, with `join` (`","` when left out), `max_length` (`100` when left out) and `lookup`, looking rows up in `lookups`. It must equal `value` (`null`: no value). With `warning: true`, a warning must be logged |
| `push.json` | Build the push of `form` and `step` (and `mode` when given) with `catalog`, `context` and `lookups`, and the settings: `null` is no file, a string is the raw file contents, an object is the parsed file. It must equal `push` (`null`: no push). `warnings` must be logged as listed; with `error`, an error must be logged |
| `output.json` | The push script of `pushes` with `variable` must equal `script` (`null`: no script). Container ids and variables must be accepted or rejected as listed |

In `lookups`, a table is a list of rows; a lookup finds the row whose `key` column equals the value.
