# form-datalayer

**Measure every form step — input, confirm, complete, error — with Google Tag Manager, from a catalog in your code and settings changed on an admin screen.**

Tag managers are easy to add and hard to keep honest. Events get hand-written in templates, forms added later are forgotten, and sooner or later a name or an email address ends up in `dataLayer`. form-datalayer turns that around:

- **A catalog in git.** The app lists its forms and pages, the steps of each, and the only values each may send. Reviewed like code; checked against a JSON Schema.
- **Every form, by default.** A form added to the catalog is measured from its first deploy, with an event name made from its key (`front.entry` → `front_entry_confirm`).
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

Write the catalog in `form-datalayer.json` at the root of the app ([spec/catalog.md](spec/catalog.md)); pages are route names:

```json
{
  "version": 1,
  "forms": {
    "front.entry": {
      "label": "Job application",
      "steps": {
        "input": [{"page": "entries.create"}],
        "confirm": [{"page": "entries.confirm"}],
        "complete": [{"page": "entries.complete"}]
      },
      "params": {
        "posting_code": {"label": "Posting code", "from": "posting.code", "default": true},
        "prefectures": {"label": "Prefectures", "from": "posting.prefecture_ids.*",
                        "lookup": {"table": "prefectures", "key": "id", "column": "name"}}
      }
    }
  }
}
```

Declare the containers in `config/form-datalayer.php`; their GTM ids are entered on the settings screen (the `default` id is used until then):

```php
'containers' => [
    'front' => ['label' => 'Public site', 'default' => ''],
    'admin' => ['label' => 'Admin', 'default' => ''],
],
```

Put the tag in each layout, naming its container:

```blade
<head>
    @formDatalayerHead('front')
</head>
<body>
    @formDatalayerBody('front')
```

Pages listed in the catalog push their step by themselves; an input page that comes back with validation errors pushes `error`. Give them values, and push steps that have no page of their own:

```php
use Otaqq\FormDatalayer\Laravel\Facades\FormDatalayer;

FormDatalayer::with(['posting' => $posting]);                   // values for this page's push
FormDatalayer::flash('admin.job-posting', 'complete', [], 'edit'); // on the next page, after a redirect
```

The settings screen is at `/form-datalayer` for users with the `form-datalayer.manage` ability, which the app defines:

```php
Gate::define('form-datalayer.manage', fn ($user) => in_array($user->role, ['system_admin', 'service_admin'], true));
```

The settings are saved to `storage/app/private/form-datalayer.json` (`FORM_DATALAYER_SETTINGS`): keep it on storage that survives deploys. Publish the view (`--tag=form-datalayer-views`) to put the screen in your layout. After a deploy, `php artisan form-datalayer:check` lists settings that name what the catalog no longer has, and parameters that are new.

## License

MIT
