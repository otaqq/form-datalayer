# Settings

The settings choose, from the catalog, what is measured: which forms are on, the event name of each step, and which parameters each step sends. They are a JSON file **outside the repository**, like `.env` ([schema/settings.schema.json](schema/settings.schema.json)), written from the settings screen ([admin.md](admin.md)) so that changes need no deploy.

The file holds data only. It is read with a JSON parser, never executed, and every name in it is checked against the catalog before anything reaches the page.

## Example

```json
{
  "version": 3,
  "updated_at": "2026-11-16T10:00:00+09:00",
  "updated_by": "admin@example.com",
  "forms": {
    "front.entry": {
      "steps": {
        "complete": {"event": "entry_complete", "params": {"posting_code": true, "prefectures": true}}
      }
    },
    "admin.job-posting": {"enabled": false}
  }
}
```

## Fields

| Field | Meaning |
|---|---|
| `version` | A whole number, raised by 1 on every save ([optimistic lock](#saving)). `0` when the file does not exist |
| `updated_at`, `updated_by` | When and by whom it was last saved (for people; not used by the rules) |
| `forms` | Form key → form settings. Forms left out use the [defaults](#defaults) |

Form settings:

| Field | Meaning |
|---|---|
| `enabled` | `false` stops measuring the form. Left out: `true` |
| `steps` | Step → step settings. Steps left out use the defaults |

Step settings:

| Field | Meaning |
|---|---|
| `event` | The event name. Left out or `null`: the default name ([push.md](push.md#event-names)). Same rules as a GA4 event name |
| `params` | Parameter name → `true` (send) or `false` (do not send), for parameters in the form's catalog `params`. Parameters left out (all of them when `params` is left out) follow their `default` |

## Defaults

Whatever the settings leave out follows the catalog:

- A form is measured (`enabled: true`), unless the catalog turns it off. A form added to the catalog is therefore measured from its first deploy, with the default event names and the `default: true` parameters only.
- A parameter the step's `params` does not mention is sent when the catalog marks it `default: true`. The screen saves every parameter it shows as `true` or `false`, so a parameter left out is one added to the catalog after the last save: the screen marks it as new.

## Settings and the catalog over time

The catalog changes with the code; the settings are not edited when it does. Reading them against a newer catalog:

| In the settings, but… | Then |
|---|---|
| the form is no longer in the catalog | It is skipped |
| the step is no longer in the form | It is skipped |
| the parameter is no longer in the form | It is not sent; the rest of the step is |
| a parameter was added to the catalog | It is not mentioned, so it follows its `default`: sent only with `default: true` |

Each skipped name is logged as a warning (`unknown form`, `unknown step`, `unknown param`), so a stale file shows up in the logs and in the check command ([admin.md](admin.md#check-command)). A renamed parameter is a removed one and a new one: choose it again.

## Broken files

| The file… | Then |
|---|---|
| does not exist | Empty settings (`version: 0`): everything follows the defaults |
| is not JSON, or fails the schema (a wrong type, an event name that breaks the rules) | **Nothing is pushed** and an error is logged. Pages still render |

Stale names are not broken files (see above): only a file that cannot be trusted stops every push.

## Where the file lives

- Outside the public directory and outside the code: the app's private storage (Laravel: `storage/app/private/form-datalayer.json`), with the path configurable.
- On storage that survives deploys. With more than one server, on storage they share.
- The web process may write that file (and the temporary files next to it) and nothing else in the app.

## Saving

A save replaces the whole file:

1. Take an exclusive lock (`flock` on a lock file next to it).
2. Read the current `version`. If it is not the version the screen was opened with, stop: someone else saved first. Show their settings and ask to save again.
3. Check the new settings against the schema and the catalog. Names not in the catalog are rejected here (on save they are mistakes, not history).
4. Write `version + 1`, `updated_at` and `updated_by` with the settings to a temporary file in the same directory, then rename it over the file. Readers see the old file or the new one, never half of one.
5. Release the lock, and log who saved, with the settings before and after.
