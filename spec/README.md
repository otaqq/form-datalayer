# Spec

What every implementation of form-datalayer does, whatever the language or the framework.

| File | What |
|---|---|
| [catalog.md](catalog.md) | The catalog: the forms of the app and the parameters they may send (in git, written by developers); pages come from routes or the catalog |
| [settings.md](settings.md) | The settings: which forms are on, event names and the chosen parameters (outside git, written from the admin screen), and how they are stored |
| [push.md](push.md) | How a push is built from the catalog, the settings and the values the app passes |
| [output.md](output.md) | The tag and the push script in the page |
| [admin.md](admin.md) | The settings screen and the check command |

The two JSON Schemas are in [schema/](schema). Patterns match the whole value: a trailing newline does not pass (in PCRE, use `\z` or the `D` modifier, not a bare `$`). [conformance/](../conformance) checks the rules with shared data.

## Words

| Word | Meaning |
|---|---|
| form | A key in the catalog or the routes for a form (`front.entry`). Its first word is its group (`front`) |
| step | Where the user is in a form: `input`, `confirm`, `complete` or `error` |
| mode | `create` or `edit`, for forms that do both |
| context | The values the app passes for one push (the request input, the record shown, …) |
| parameter | A value sent with the event, taken from the context and allowed by the catalog |
