# Settings screen and check command

## Who

The app decides who may open the screen and save (Laravel: a Gate ability, `form-datalayer.manage`). The package defines no roles.

## Screen

One list of every form in the catalog, in catalog order:

- Forms the catalog turns off are shown as off by the app, and cannot be changed.
- Each other form has an on / off switch, and each of its steps (`error` included for forms):
  - the event name, empty for the default name (shown as a placeholder),
  - one checkbox per parameter of the form, labelled with the catalog `label` and the parameter name.

There is no free text but the event names. Parameters and forms can only be chosen from the catalog.

What the screen shows against the saved settings ([settings.md](settings.md#settings-and-the-catalog-over-time)):

| Mark | When | Checkbox |
|---|---|---|
| (none) | The parameter is saved as `true` or `false` | As saved |
| (none) | The step has not been saved yet | Checked when `default: true` |
| **new** | The step was saved with its parameters, but not this one (added to the catalog since) | Checked when `default: true` |
| **removed** | The saved settings name a form, step or parameter that is no longer in the catalog | Not shown as a checkbox; listed so that people see it went away. The next save drops it |

Saving writes every form and step shown, and every parameter as `true` or `false`, then follows [settings.md](settings.md#saving). A save that loses to another one (the version changed) shows the other settings and keeps what was typed, to save again.

## Check command

Reads the catalog and the settings and reports:

- the catalog's problems (it is rejected),
- whether the settings file is broken,
- every removed form, step or parameter still in the settings,
- every new parameter (as on the screen).

It exits with a failure only for a rejected catalog or a broken settings file, so it can run after a deploy. Laravel: `php artisan form-datalayer:check`.
