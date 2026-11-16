# Catalog

The catalog lists every form the app can measure, and every parameter each one may send. It is a JSON file in the app's repository ([schema/catalog.schema.json](schema/catalog.schema.json)), written and reviewed like code. Nothing outside the catalog can be sent: the settings ([settings.md](settings.md)) only choose from it.

Only forms are measured. Pages that are not forms (an article, the terms of use, search results) are left to the page views of the analytics tag, which carry the URL and the title. A push is for what the URL does not tell: which step of which form the user is on, and the values the app allows.

The catalog never holds personal data rules of its own. Whether a value is personal (a name, an email address, the text of a message) is the app's call: the app lists only the values it is willing to send.

## Forms and pages

A form has steps: `input`, `confirm` and `complete`, shown on pages. `error` is not listed: a form with `input` has it ([push.md](push.md#steps)).

The pages of each step come from one of two places, or both:

- **The framework's routes** (preferred). The adapter reads them from metadata on each route ([push.md](push.md#pages-from-routes)). Adding or removing a route adds or removes the page, with no change to the catalog.
- **The catalog's `steps`**, for frameworks without route metadata.

A form that only routes name is still a form: its label is its key and it sends no parameters until the catalog lists some.

## Example

```json
{
  "$schema": "https://raw.githubusercontent.com/otaqq/form-datalayer/main/spec/schema/catalog.schema.json",
  "version": 1,
  "forms": {
    "front.entry": {
      "label": "Job application",
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
        "confirm": [{"page": "admin.job-postings.confirm"}]
      },
      "params": {
        "role": {"label": "Role", "from": "user.role", "default": true}
      }
    }
  }
}
```

## Fields

`version` is `1`.

`forms` maps a **form key** to a form. A key is lower-case ASCII words joined by `.` or `-` (`front.entry`, `admin.master-data`), at most 64 characters. Its first word is the form's **group** (`front`, `admin`): the settings screen shows one tab per group.

| Field | Required | Meaning |
|---|---|---|
| `label` | yes | Shown on the settings screen |
| `enabled` | no (`true`) | `false` turns the form off in the app, whatever the settings say |
| `steps` | no | Pages by step, for frameworks without route metadata |
| `params` | no (`{}`) | The parameters the form may send |

### Steps

`steps` maps `input`, `confirm` or `complete` to a list of pages. A page is `{"page": "<id>", "mode": "create" | "edit"}`; `mode` is optional.

- `page` is an id the framework adapter matches against the current request (Laravel: the route name).
- The same page may not appear in two steps or two forms, in the catalog or the routes.

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

An implementation rejects a catalog (with the pages from routes) that fails the schema, and also one where:

- a default event name breaks the event name rules: longer than 40 characters, or starting with `google_`, `ga_` or `firebase_` ([push.md](push.md#event-names)),
- the same page appears twice,
- a route names a step that is not `input`, `confirm` or `complete`, or a key that is not a form key.

A rejected catalog is a programming error: fail loudly (an exception at boot or in the test), never silently measure less.
