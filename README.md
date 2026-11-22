# form-datalayer

**Measure every form step — input, confirm, complete, error — with Google Tag Manager, from routes and a catalog in your code, and settings changed on an admin screen.**

Tag managers are easy to add and hard to keep honest. Events get hand-written in templates, forms added later are forgotten, and sooner or later a name or an email address ends up in `dataLayer`. form-datalayer turns that around:

- **A catalog in git.** The app lists its forms and pages, the steps of each, and the only values each may send. Reviewed like code; checked against a JSON Schema.
- **Pages from routes.** Mark a route with its form and step; added and removed routes show up on the settings screen by themselves. Event names are made from the key (`front.entry` → `front_entry_confirm`).
- **Nothing sent unless allowed.** No request input reaches the page unless the catalog lists it. Ids can be turned into names (`prefecture_id` → `Tokyo`).
- **Settings without a deploy.** The GTM container ids, which forms are on, event names, and which parameters each step sends are chosen on an admin screen — checkboxes from the catalog, no free scripts — and saved to a JSON file outside the repository.
- **Safe to change over time.** Parameters removed from the catalog stop being sent; new ones wait to be chosen; stale settings never break a page.
- **One spec, many languages.** The rules live in [spec/](spec) and are checked by shared data in [conformance/](conformance). PHP (with Laravel) comes first.

> **Status:** v0.1: PHP and Laravel, in use in one application. Other languages are planned; see [docs/roadmap.md](docs/roadmap.md).

## Layout

| Path | What | Package |
|---|---|---|
| [spec](spec) | What every implementation does, and the JSON Schemas | — |
| [conformance](conformance) | Shared inputs and expected results | — |
| `lang/php/core` | Framework-independent PHP: catalog, settings, pushes, output | Composer `otaqq/form-datalayer` |
| `lang/php/laravel` | Laravel: config, steps from route names, flash, Blade, settings screen, check command | Composer `otaqq/form-datalayer` |

## Laravel

```sh
composer require otaqq/form-datalayer
php artisan vendor:publish --tag=form-datalayer-config
```

Mark the pages of each form on its routes. Adding or removing a route adds or removes the page; nothing else to keep in sync:

```php
Route::get('entry/{posting}', [EntryController::class, 'create'])->name('entries.create')->dataLayer('front.entry', 'input');
Route::post('entry/{posting}/confirm', [EntryController::class, 'confirm'])->dataLayer('front.entry', 'confirm');
Route::post('entry/{posting}', [EntryController::class, 'store'])->dataLayer('front.entry', 'complete');   // saves, redirects to the complete page
Route::get('entry/{posting}/complete', [EntryController::class, 'complete']);

Route::get('prefectures/create', …)->dataLayer('admin.prefecture', 'input', mode: 'create');
Route::get('prefectures/{id}/edit', …)->dataLayer('admin.prefecture', 'input', mode: 'edit');
Route::post('prefectures/confirm', …)->dataLayer('admin.prefecture', 'complete');   // redirects to the list
```

- An `input` page that comes back with validation errors pushes `error`.
- A `complete` route that submits (POST, PUT, PATCH, DELETE) and redirects pushes `complete` on the next page, unless it flashed validation errors or one of the flash keys in `failures` (`'failures' => ['error']`). Set its mode with `FormDatalayer::mode('edit')`.
- List in `failures` every flash key a failed submit redirects with (an expired token sent back with `notice`): any other redirect of a submitting `complete` route counts as a completion.
- A GET route marked `complete` (a complete page) pushes `complete` only when it renders. When it redirects (a reload, a bookmark or a bot opened it outside the flow) it pushes nothing. Prefer marking the submitting route, as above, so a reload of the complete page never pushes; mark one of the two, not both.

Write the labels and the values each form may send in `form-datalayer.json` at the root of the app ([spec/catalog.md](spec/catalog.md)). Only these values can ever reach the page:

```json
{
  "version": 1,
  "forms": {
    "front.entry": {
      "label": "Job application",
      "params": {
        "posting_code": {"label": "Posting code", "from": "posting.code", "default": true},
        "prefectures": {"label": "Prefectures", "from": "posting.prefecture_ids.*",
                        "lookup": {"table": "prefectures", "key": "id", "column": "name"}}
      }
    }
  }
}
```

Give the values from controllers:

```php
use Otaqq\FormDatalayer\Laravel\Facades\FormDatalayer;

FormDatalayer::with(['posting' => $posting]);   // values for this request's pushes
```

Declare the containers and the tab labels in `config/form-datalayer.php`. The GTM id is entered on the settings screen; a container that `follows` another is a checkbox there (one GTM container, chosen per area):

```php
'containers' => [
    'front' => ['label' => 'Public site', 'default' => ''],
    'admin' => ['label' => 'Admin screen', 'follows' => 'front'],
],
'groups' => ['front' => 'Public site', 'admin' => 'Admin screen'],
```

Put the tag in each layout, naming its container:

```blade
<head>
    @formDatalayerHead('front')
</head>
<body>
    @formDatalayerBody('front')
```

The settings screen is at `/form-datalayer` for users with the `form-datalayer.manage` ability, which the app defines. It lists the forms in one tab per group (the first word of the key); each form has a page for its event names and values. **Forms are off until checked there**; new forms are marked as new.

```php
Gate::define('form-datalayer.manage', fn ($user) => in_array($user->role, ['system_admin', 'service_admin'], true));
```

The settings are saved to `storage/app/private/form-datalayer.json` (`FORM_DATALAYER_SETTINGS`): keep it on storage that survives deploys. Publish the views (`--tag=form-datalayer-views`: `index` and `form`) to put the screen in your layout. After a deploy, `php artisan form-datalayer:check` lists new forms and parameters, and settings that name what is gone.

## Upgrading from v0.3

- **A GET route marked `complete` no longer pushes `complete` when it redirects.** Its redirect means the complete page could not be shown (a reload, a bookmark or a bot), so it was counted wrongly. If the complete page comes after a POST, move the mark to that POST. If a GET action completes something itself and then redirects (a payment return URL, an email verification link, an OAuth callback), call `FormDatalayer::flash($form, 'complete', …)` in the action.

## Upgrading from v0.2

- **Forms are off until turned on.** Forms the saved settings do not turn on (`"enabled": true`) stop pushing. Check them on the list and save.
- Pages that are not forms (`view`) are gone: drop them from the catalog. Their saved settings are listed as removed and dropped on the next save.
- Move `steps` from the catalog to the routes (`->dataLayer()`); a page may not be in both. Calls to `FormDatalayer::flash(…, 'complete', …)` can become `->dataLayer(…, 'complete')` on the submitting route (POST, PUT, PATCH, DELETE) that redirects; on a GET route keep `flash()`.
- The settings view is now two views, `index` and `form`: publish them again if you had published `settings`.

## License

MIT
