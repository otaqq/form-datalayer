# Push

A push is one object added to `dataLayer` for one step of one form:

```js
{"event": "front_entry_confirm", "form": "front.entry", "step": "confirm", "posting_code": "241116ab", "prefectures": "Tokyo,Osaka"}
```

## Steps

The framework adapter finds the step of the current request from the pages (the catalog's and the routes'):

- The page of the request is listed under a step: that step, with the page's `mode` if it has one.
- The page is an `input` page and the request carries the validation errors of the previous submit (Laravel: the `errors` in the session): `error` instead of `input`. `error` has the same pages and mode as `input`.
- The page is not in the catalog: no push.

## Pages from routes

Where the framework lets routes carry metadata, the adapter takes the pages from there: each route names its form, its step and, for `input`, its mode. Laravel:

```php
Route::get('entry/{posting}', …)->name('entries.create')->dataLayer('front.entry', 'input');
Route::get('prefectures/{id}/edit', …)->dataLayer('admin.prefecture', 'input', mode: 'edit');
Route::post('prefectures/confirm', …)->dataLayer('admin.prefecture', 'complete');
```

The page id of a route is its name, or its method, domain and URI when it has none.

A route marked `complete` handles the final submit. When its own action answers with a redirect (a redirect from a route middleware, such as a password confirmation, does not count) and has not flashed a failure (validation errors, or the flash keys the app lists as failures), the adapter pushes `complete` on the next page, with the context the request gave (`with()`) and the mode the app set for it, if any (Laravel: `FormDatalayer::mode('edit')`). The app writes nothing else for the step. A route marked `complete` that answers with a page (no redirect) pushes `complete` on that page.

## Pushing a step

The app can also push a step itself, with the form key, the step, the context and the mode if it knows it:

- **Now**: the push goes into the page being rendered.
- **On the next page** (flash): for a step shown after a redirect, when routes cannot say it. A form saved in the admin screen redirects to a list; the app pushes `complete` when it saves, and the push appears on the list.

A form key or step that is not in the catalog is a programming error (an exception). A page may carry several pushes; they keep their order.

## Building the push

1. Stop with no push when any of these is true:
   - the catalog turns the form off (`enabled: false`),
   - the settings file is broken ([settings.md](settings.md#broken-files)),
   - the settings do not turn the form on (forms are off by default, [settings.md](settings.md#defaults)).
2. `event` is the step's event in the settings, or the [default name](#event-names).
3. The object starts with `event`, `form` (the key), `step`, then `mode` when it is known.
4. Each parameter of the form, in catalog order, is added when it is chosen ([settings.md](settings.md#defaults)) and has a [value](#values). Parameters with no value are left out, not sent empty.

## Event names

The default name of a step is made from the form key, with `.` and `-` replaced by `_`:

`<key>_<step>`: `front.entry` → `front_entry_input`, `front_entry_confirm`, `front_entry_complete`, `front_entry_error`.

A catalog whose default names are longer than 40 characters is rejected ([catalog.md](catalog.md#checks)); shorten the key.

## Values

The **context** is what the app passes for a push: a tree of maps, lists and plain values (strings, numbers, `true` / `false`, `null`). The framework adapter may add values of its own; Laravel adds `input` (the request input) and `user` (the attributes of the signed-in user the app lists). Values the app passes win over those the adapter adds; maps are merged key by key, lists are replaced. Values reach the page only through the parameters of the catalog.

A parameter's value is found in five steps:

1. **Path.** Follow `from` one name at a time. `*` takes every item of a list and continues in each; the result is then a list (nested `*` flatten into one list). `*` on something that is not a list gives no value. A missing name, or a name on something that is not a map, gives no value; under `*`, that item is dropped.
2. **Plain values only.** `null` gives no value (or is dropped from a list). A map or a list where a plain value is expected gives no value, and a warning is logged.
3. **Lookup.** With `lookup`, each value is replaced by `column` of the row whose `key` equals it; values with no row are dropped. All values of one push are looked up with one query per table.
4. **Lists.** Repeated values are dropped (the first one stays). An empty list gives no value. Unless `join` is `null`, the list becomes one string: each value as text (`true` / `false`, numbers in their shortest form) joined with `join`.
5. **Length.** A string longer than `max_length` characters (Unicode code points) is cut to that length.

A value that is not a list is sent as it is: a string, a number, or `true` / `false`.
