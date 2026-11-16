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
use Otaqq\FormDatalayer\Settings;
use Otaqq\FormDatalayer\SettingsStore;

/**
 * The settings screen (spec/admin.md#screen): the list of forms by tab, and one page per form.
 * Who may use it is the app's form-datalayer.manage ability.
 */
final class SettingsController
{
    public function __construct(private readonly FormDatalayer $datalayer, private readonly SettingsStore $store, private readonly Config $config) {}

    public function index(Request $request): View
    {
        $settings = $this->store->read();
        $screen = $this->screen($settings);
        $groups = $screen->groups();
        $asked = $request->query('group');
        $group = is_string($asked) && isset($groups[$asked]) ? $asked : (string) array_key_first($groups);

        return view('form-datalayer::index', [
            'containers' => $screen->containers(),
            'groups' => $groups,
            'group' => $group,
            'forms' => $group === '' ? [] : $screen->forms($group),
            ...$this->common($screen, $settings),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $screen = $this->screen($this->store->read());
        $group = is_string($request->input('group')) ? $request->input('group') : '';

        return $this->save($request, $screen->fromList((array) $request->input('containers', []), (array) $request->input('enabled', []), $group));
    }

    public function show(string $form): View
    {
        abort_unless($this->datalayer->catalog()->has($form), 404);
        $settings = $this->store->read();
        $screen = $this->screen($settings);

        return view('form-datalayer::form', [
            'form' => $screen->form($form),
            'groupLabel' => $screen->groups()[$this->datalayer->catalog()->form($form)->group()],
            ...$this->common($screen, $settings),
        ]);
    }

    public function updateForm(Request $request, string $form): RedirectResponse
    {
        abort_unless($this->datalayer->catalog()->has($form), 404);
        $screen = $this->screen($this->store->read());

        return $this->save($request, $screen->fromForm($form, $request->only(['enabled', 'steps'])));
    }

    private function screen(Settings $settings): Screen
    {
        return new Screen($this->datalayer->catalog(), $settings, $this->datalayer->containers(), (array) $this->config->get('form-datalayer.groups', []));
    }

    /**
     * @return array<string, mixed>
     */
    private function common(Screen $screen, Settings $settings): array
    {
        return [
            'removed' => $screen->removed(),
            'version' => $settings->version,
            'broken' => $settings->broken,
            'updatedAt' => $settings->updatedAt,
            'updatedBy' => $settings->updatedBy,
            'routeName' => (string) $this->config->get('form-datalayer.screen.name', 'form-datalayer.'),
        ];
    }

    /**
     * @param  array{containers: array<string, string>, forms: array<string, mixed>}  $settings
     */
    private function save(Request $request, array $settings): RedirectResponse
    {
        $attribute = (string) $this->config->get('form-datalayer.screen.user_name', 'email');
        $by = (string) ($request->user()?->{$attribute} ?? $request->user()?->getAuthIdentifier() ?? '');

        try {
            $this->store->save($settings['forms'], (int) $request->input('version'), $by, containers: $settings['containers']);
        } catch (SettingsConflict) {
            return back()->withInput($request->except('version'))->with('form-datalayer.conflict', true);
        } catch (InvalidSettings $e) {
            return back()->withInput($request->except('version'))->withErrors(['form-datalayer' => $e->errors]);
        }

        return back()->with('form-datalayer.saved', true);
    }
}
