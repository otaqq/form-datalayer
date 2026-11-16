<?php

declare(strict_types=1);

namespace Otaqq\FormDatalayer\Laravel\Http;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Otaqq\FormDatalayer\Exception\InvalidSettings;
use Otaqq\FormDatalayer\Exception\SettingsConflict;
use Otaqq\FormDatalayer\Laravel\FormDatalayer;
use Otaqq\FormDatalayer\Laravel\Screen;
use Otaqq\FormDatalayer\SettingsStore;

/**
 * The settings screen (spec/admin.md#screen). Who may use it is the app's form-datalayer.manage ability.
 */
final class SettingsController
{
    public function __construct(private readonly FormDatalayer $datalayer, private readonly SettingsStore $store, private readonly Config $config) {}

    public function edit(): View
    {
        $settings = $this->store->read();
        $screen = new Screen($this->datalayer->catalog(), $settings);

        return view('form-datalayer::settings', [
            'forms' => $screen->forms(),
            'removed' => $screen->removed(),
            'version' => $settings->version,
            'broken' => $settings->broken,
            'updatedAt' => $settings->updatedAt,
            'updatedBy' => $settings->updatedBy,
            'routeName' => (string) $this->config->get('form-datalayer.screen.name', 'form-datalayer.'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $settings = $this->store->read();
        $screen = new Screen($this->datalayer->catalog(), $settings);
        $forms = $screen->toSave((array) $request->input('forms', []));
        $attribute = (string) $this->config->get('form-datalayer.screen.user_name', 'email');
        $by = (string) ($request->user()?->{$attribute} ?? $request->user()?->getAuthIdentifier() ?? '');

        try {
            $this->store->save($forms, (int) $request->input('version'), $by);
        } catch (SettingsConflict) {
            return back()->withInput($request->except('version'))->with('form-datalayer.conflict', true);
        } catch (InvalidSettings $e) {
            return back()->withInput($request->except('version'))->withErrors(['form-datalayer' => $e->errors]);
        }

        return back()->with('form-datalayer.saved', true);
    }
}
