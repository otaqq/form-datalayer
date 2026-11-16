# form-datalayer

**Measure every form step — input, confirm, complete, error — with Google Tag Manager, from a catalog in your code and settings changed on an admin screen.**

Tag managers are easy to add and hard to keep honest. Events get hand-written in templates, forms added later are forgotten, and sooner or later a name or an email address ends up in `dataLayer`. form-datalayer turns that around:

- **A catalog in git.** The app lists its forms and pages, the steps of each, and the only values each may send. Reviewed like code; checked against a JSON Schema.
- **Every form, by default.** A form added to the catalog is measured from its first deploy, with an event name made from its key (`front.entry` → `front_entry_confirm`).
- **Nothing sent unless allowed.** No request input reaches the page unless the catalog lists it. Ids can be turned into names (`prefecture_id` → `Tokyo`).
- **Settings without a deploy.** Which forms are on, event names, and which parameters each step sends are chosen on an admin screen from the catalog — checkboxes, no free scripts — and saved to a JSON file outside the repository.
- **Safe to change over time.** Parameters removed from the catalog stop being sent; new ones wait to be chosen; stale settings never break a page.
- **One spec, many languages.** The rules live in [spec/](spec) and are checked by shared data in [conformance/](conformance). PHP (with Laravel) comes first.

> **Status:** designing v0.1. See [docs/roadmap.md](docs/roadmap.md).

## Layout

| Path | What | Package |
|---|---|---|
| [spec](spec) | What every implementation does, and the JSON Schemas | — |
| [conformance](conformance) | Shared inputs and expected results | — |
| `lang/php/core` | Framework-independent PHP: catalog, settings, pushes, output | Composer `otaqq/form-datalayer` |
| `lang/php/laravel` | Laravel: config, steps from route names, flash, Blade, settings screen, check command | Composer `otaqq/form-datalayer` |

## License

MIT
