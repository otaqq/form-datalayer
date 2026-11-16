# Roadmap

## v0.1: PHP and Laravel, Google Tag Manager

- [x] Spec: catalog, settings (stored outside git), pushes, output, settings screen and check command
- [x] JSON Schemas for the catalog and the settings
- [x] Conformance data: catalog, values, push, output
- [x] PHP core: catalog and settings (schema and catalog checks), values, pushes, output
- [x] PHP core: the settings store (lock, version, atomic write)
- [x] Laravel: config, steps from route names, `error` from validation errors, flash, Blade directives
- [x] Laravel: lookups with the query builder, one query per table
- [x] Laravel: settings screen (publishable views, Gate ability) and `form-datalayer:check`
- [x] First user: an application with public and admin forms
- [x] Tag v0.1.0

## v0.2: container ids on the settings screen

- [x] Settings: `containers` (name → GTM id), checked on read and save; the app declares the names, with a default id
- [x] Settings screen: one field per container; removed containers listed; check command reports them

## Languages

Each follows [spec/](../spec) and passes [conformance/](../conformance). Check that names are free before publishing.

- [x] PHP: Composer `otaqq/form-datalayer` (with Laravel)
- [ ] Node.js, Ruby, Python, Go

## Later

- Other outputs: Matomo Tag Manager (`_mtm`), `gtag('event', …)`
- Settings stored in a database, for apps on more than one server without shared storage
