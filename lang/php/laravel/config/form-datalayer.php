<?php

/*
 * form-datalayer: measure every form step with Google Tag Manager (spec/ of otaqq/form-datalayer).
 */
return [

    // The catalog: forms, steps, pages (route names) and the parameters they may send. In git.
    'catalog' => base_path('form-datalayer.json'),

    // The settings written from the settings screen. Outside git and the public directory, on storage that survives deploys.
    // An empty FORM_DATALAYER_SETTINGS= uses the default too
    'settings' => env('FORM_DATALAYER_SETTINGS') ?: storage_path('app/private/form-datalayer.json'),

    // GTM containers by name, for @formDatalayerHead('default') and @formDatalayerBody('default').
    // Their ids are entered on the settings screen; `default` is used until then (empty: no tag and no pushes).
    // A container with 'follows' => 'default' gets the same id, chosen with a checkbox (e.g. an admin area).
    'containers' => [
        'default' => ['label' => 'Site', 'default' => ''],
    ],

    // Labels of the tabs of the settings screen, by group (the first word of the form keys).
    'groups' => [],

    // Flash keys that mean a `complete` route failed, besides validation errors (e.g. 'error').
    'failures' => [],

    // The data layer variable.
    'variable' => 'dataLayer',

    // Attributes of the signed-in user added to the context as `user.<attribute>`. Only parameters of the catalog send them.
    'user' => [],

    // Database connection for `lookup` (null: the default connection).
    'connection' => null,

    'screen' => [
        'enabled' => true,
        'prefix' => 'form-datalayer',
        'name' => 'form-datalayer.',
        // The app defines the form-datalayer.manage ability (Gate::define); without it, everyone is refused.
        'middleware' => ['web', 'auth', 'can:form-datalayer.manage'],
        // The user attribute saved as updated_by.
        'user_name' => 'email',
    ],
];
