# Catalog

The catalog lists every form and page the app can measure, and every parameter each one may send. It is a JSON file in the app's repository ([schema/catalog.schema.json](schema/catalog.schema.json)), written and reviewed like code. Nothing outside the catalog can be measured or sent: the settings ([settings.md](settings.md)) only choose from it.

The catalog never holds personal data rules of its own. Whether a value is personal (a name, an email address, the text of a message) is the app's call: the app lists only the values it is willing to send.

## Example

```json
{
  "$schema": "https://raw.githubusercontent.com/otaqq/form-datalayer/main/spec/schema/catalog.schema.json",
  "version": 1,
  "forms": {
    "front.entry": {
      "label": "Job application",
      "steps": {
        "input": [{"page": "front.entries.create"}],
        "confirm": [{"page": "front.entries.confirm"}],
        "complete": [{"page": "front.entries.complete"}]
      },
      "params": {
        "posting_code": {"label": "Posting code", "from": "posting.code", "default": true},
        "prefectures": {
          "label": "Prefectures",
          "from": "posting.locations.*.prefecture_id",
          "lookup": {"table": "prefectures", "key": "id", "column": "name"}
        }
      }
    },
    "admin.job-posting": {
      "label": "Job posting",
      "steps": {
        "input": [{"page": "admin.job-postings.create", "mode": "create"}, {"page": "admin.job-postings.edit", "mode": "edit"}],
        "confirm": [{"page": "admin.job-postings.confirm"}],
        "complete": []
      },
      "params": {
        "role": {"label": "Role", "from": "user.role"}
      }
    },
    "front.job-search": {
      "label": "Job search results",
      "enabled": false,
      "steps": {"view": [{"page": "front.jobs.index"}]},
      "params": {
        "result_count": {"label": "Results", "from": "result_count", "default": true},
        "has_free_word": {"label": "Free word used", "from": "has_free_word"}
      }
    }
  }
}
```

## Fields

`version` is `1`.

`forms` maps a **form key** to a form. A key is lower-case ASCII words joined by `.` or `-` (`front.entry`, `admin.master-data`), at most 64 characters.

| Field | Required | Meaning |
|---|---|---|
| `label` | yes | Shown on the settings screen |
| `enabled` | no (`true`) | `false` turns the form off in the app, whatever the settings say |
| `steps` | yes | The steps the form has, each with the pages that show it |
| `params` | no (`{}`) | The parameters the form may send |

### Steps

A form has **form steps** or **a page step**, never both:

- Form steps: `input` (required), `confirm`, `complete`. `error` is not listed: every form with `input` has it ([push.md](push.md#steps)).
- Page step: `view` alone, for pages that are not forms (a job shown, search results).

Each step lists its pages. A page is `{"page": "<id>", "mode": "create" | "edit"}`; `mode` is optional.

- `page` is an id the framework adapter matches against the current request (Laravel: the route name). The catalog does not care what it means.
- A step may list no pages (`"complete": []`). The app then pushes that step itself, typically after a redirect ([push.md](push.md#pushing-a-step)).
- The same page may not appear in two steps or two forms.

### Parameters

`params` maps a **parameter name** to how its value is found.

A parameter name follows the GA4 rules: an ASCII letter, then letters, digits or `_`, at most 40 characters. It may not start with `google_`, `ga_` or `firebase_`, and may not be `event`, `form`, `step` or `mode` (sent with every push).

| Field | Required | Meaning |
|---|---|---|
| `label` | yes | Shown on the settings screen |
| `from` | yes | A path into the context ([push.md](push.md#values)): names joined by `.`, where `*` takes every item of a list (`posting.locations.*.prefecture_id`) |
| `lookup` | no | Turns ids into names: `{"table", "key", "column"}` reads `column` from the row of `table` whose `key` is the value. Identifiers are ASCII letters, digits and `_` (`table` may be `schema.table`) |
| `join` | no (`","`) | How a list is sent: joined into one string with this separator, or kept as a list with `null` |
| `max_length` | no (`100`) | Longer strings are cut to this many characters (GA4 drops longer values). `null` for no limit |
| `default` | no (`false`) | Checked when the settings have not chosen yet, and when the parameter is new ([settings.md](settings.md#defaults)) |

## Checks

An implementation rejects a catalog that fails the schema, and also one where:

- the default event name of a step breaks the event name rules: longer than 40 characters, or starting with `google_`, `ga_` or `firebase_` ([push.md](push.md#event-names)),
- the same page appears twice.

A rejected catalog is a programming error: fail loudly (an exception at boot or in the test), never silently measure less.
