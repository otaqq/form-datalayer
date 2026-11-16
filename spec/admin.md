# Settings screen and check command

## Who

The app decides who may open the screen and save (Laravel: a Gate ability, `form-datalayer.manage`). The package defines no roles.

## Screen

Two pages: a list and one page per form.

### List

- The containers the app declares, at the top: one text field per container for its GTM id (empty for none), or one checkbox per container that follows another ([output.md](output.md#containers)).
- One tab per **group** (the first word of the form keys: `front`, `admin`), labelled by the app or by the group itself. The first tab is open unless another is chosen.
- In each tab, one row per form, in catalog order then route order: an on / off checkbox, the label and key, the number of steps it has, the number of parameters it sends, a link to its page, and marks:

| Mark | When |
|---|---|
| **new** | The form is not in the saved settings (added since the last save). It is off |
| **new parameters** | The form was saved, but some of its parameters were added since ([settings.md](settings.md#defaults)) |
| **off by the app** | The catalog turns it off; its checkbox cannot be changed |

Saving the list writes the containers and the on / off of every form in the open tab; other tabs keep what was saved.

### Form

One form: its on / off checkbox, then one row per step (`error` included):

- the event name, empty for the default name (shown as a placeholder),
- one checkbox per parameter of the form, labelled with the catalog `label` and the parameter name; a parameter added since the last save is marked **new** and checked when `default: true`.

Saving the form writes that form alone: on / off, every step shown and every parameter as `true` or `false`.

### Both pages

- There is no free text but the event names and the container ids, both checked against their rules. Forms and parameters can only be chosen from the catalog and the routes.
- Saved names that are gone (a container the app no longer declares, a form or a parameter no longer in the catalog or the routes) are listed as **removed**, so people see they went away. The next save drops them.
- Every save follows [settings.md](settings.md#saving): a save that loses to another one (the version changed) shows the other settings and keeps what was typed, to save again.

## Check command

Reads the catalog, the routes and the settings and reports:

- the catalog's problems (it is rejected),
- whether the settings file is broken,
- every removed container, form or parameter still in the settings,
- every new form, and every new parameter of a saved form,
- forms in the catalog that no route or catalog page shows.

It exits with a failure only for a rejected catalog or a broken settings file, so it can run after a deploy. Laravel: `php artisan form-datalayer:check`.
