<?php

declare(strict_types=1);

namespace Otaqq\FormDatalayer\Laravel;

use Otaqq\FormDatalayer\Catalog;
use Otaqq\FormDatalayer\Form;
use Otaqq\FormDatalayer\Names;
use Otaqq\FormDatalayer\Settings;

/**
 * What the settings screen shows and saves (spec/admin.md#screen): the list of forms by group (tabs),
 * one form's page, and the containers. Every save writes the whole file, with names that are gone dropped.
 */
final class Screen
{
    /**
     * @param  array<string, array{label: string, default: string, follows: string|null}>  $containers  The containers the app declares
     * @param  array<string, string>  $groupLabels  Tab labels by group
     */
    public function __construct(
        private readonly Catalog $catalog,
        private readonly Settings $settings,
        private readonly array $containers = [],
        private readonly array $groupLabels = [],
    ) {}

    /**
     * The containers as the screen shows them: a GTM id field, or a checkbox for a container that follows another.
     *
     * @return list<array{name: string, label: string, id: string, follows: string|null, checked: bool}>
     */
    public function containers(): array
    {
        $rows = [];
        foreach ($this->containers as $name => $container) {
            $id = $this->settings->container($name) ?? $container['default'];
            $rows[] = ['name' => $name, 'label' => $container['label'], 'id' => $id, 'follows' => $container['follows'], 'checked' => $id !== ''];
        }

        return $rows;
    }

    /**
     * The tabs: group → label, in the order the groups first appear.
     *
     * @return array<string, string>
     */
    public function groups(): array
    {
        $groups = [];
        foreach ($this->catalog->forms() as $form) {
            $groups[$form->group()] ??= $this->groupLabels[$form->group()] ?? $form->group();
        }

        return $groups;
    }

    /**
     * The rows of one tab.
     *
     * @return list<array{key: string, label: string, locked: bool, enabled: bool, new: bool, new_params: bool, steps: int, params: int}>
     */
    public function forms(string $group): array
    {
        $rows = [];
        foreach ($this->catalog->forms() as $key => $form) {
            if ($form->group() !== $group) {
                continue;
            }
            $sent = [];
            $newParams = false;
            foreach ($form->stepNames() as $step) {
                foreach ($form->params as $name => $param) {
                    if ($this->settings->param($key, $step, $name) ?? $param->default) {
                        $sent[$name] = true;
                    }
                    $newParams = $newParams || $this->settings->isNewParam($key, $step, $name);
                }
            }
            $rows[] = [
                'key' => $key,
                'label' => $form->label,
                'locked' => ! $form->enabled,
                'enabled' => $form->enabled && $this->settings->formEnabled($key),
                'new' => $form->enabled && $this->settings->isNewForm($key),
                'new_params' => $newParams,
                'steps' => count($form->stepNames()),
                'params' => count($sent),
            ];
        }

        return $rows;
    }

    /**
     * One form's page: its on / off and its steps with event names and parameters.
     *
     * @return array{key: string, label: string, group: string, locked: bool, enabled: bool, steps: list<array{step: string, event: string, default_event: string, params: list<array{name: string, label: string, checked: bool, new: bool}>}>}
     */
    public function form(string $key): array
    {
        $form = $this->catalog->form($key);
        $steps = [];
        foreach ($form->stepNames() as $step) {
            $params = [];
            foreach ($form->params as $name => $param) {
                $params[] = [
                    'name' => $name,
                    'label' => $param->label,
                    'checked' => $this->settings->param($key, $step, $name) ?? $param->default,
                    'new' => $this->settings->isNewParam($key, $step, $name),
                ];
            }
            $steps[] = [
                'step' => $step,
                'event' => $this->settings->event($key, $step) ?? '',
                'default_event' => Names::defaultEventName($key, $step),
                'params' => $params,
            ];
        }

        return [
            'key' => $key,
            'label' => $form->label,
            'group' => $form->group(),
            'locked' => ! $form->enabled,
            'enabled' => $form->enabled && $this->settings->formEnabled($key),
            'steps' => $steps,
        ];
    }

    /**
     * The names in the saved settings that are gone (`unknown container: …`, `unknown form: …`, …).
     *
     * @return list<string>
     */
    public function removed(): array
    {
        return $this->settings->isBroken() ? [] : $this->settings->stale($this->catalog, array_keys($this->containers));
    }

    /**
     * The settings to save from the list: the containers, and on / off for the forms of one tab.
     *
     * @param  array<mixed>  $containers  `containers.<name>`: a GTM id, or '1' for a checked follower
     * @param  array<mixed>  $enabled  `enabled.<key>`: '1' or '0'
     * @return array{containers: array<string, string>, forms: array<string, mixed>}
     */
    public function fromList(array $containers, array $enabled, string $group): array
    {
        $forms = $this->savedForms();
        foreach ($this->catalog->forms() as $key => $form) {
            if ($form->group() === $group && $form->enabled) {
                $forms[$key] = ['enabled' => filter_var($enabled[$key] ?? false, FILTER_VALIDATE_BOOLEAN)] + ($forms[$key] ?? []);
            }
        }

        return ['containers' => $this->containersFrom($containers), 'forms' => $forms];
    }

    /**
     * The settings to save from one form's page: that form from the input, the rest as saved.
     *
     * @param  array<mixed>  $input  `enabled`, `steps.<step>.event`, `steps.<step>.params.<name>`
     * @return array{containers: array<string, string>, forms: array<string, mixed>}
     */
    public function fromForm(string $key, array $input): array
    {
        $forms = $this->savedForms();
        $form = $this->catalog->form($key);
        if ($form->enabled) {
            $saved = $forms[$key]['steps'] ?? [];
            $forms[$key] = $this->formFromInput($form, $input);
            // Steps the page does not show (pushed by the app itself) keep what was saved
            $forms[$key]['steps'] += $saved;
        }

        return ['containers' => $this->savedContainers(), 'forms' => $forms];
    }

    /**
     * @param  array<mixed>  $input
     * @return array<string, mixed>
     */
    private function formFromInput(Form $form, array $input): array
    {
        $steps = [];
        foreach ($form->stepNames() as $step) {
            $stepInput = is_array($input['steps'][$step] ?? null) ? $input['steps'][$step] : [];
            $settings = [];
            $event = is_string($stepInput['event'] ?? null) ? trim($stepInput['event']) : '';
            if ($event !== '') {
                $settings['event'] = $event;
            }
            $settings['params'] = [];
            foreach (array_keys($form->params) as $name) {
                $settings['params'][$name] = filter_var($stepInput['params'][$name] ?? false, FILTER_VALIDATE_BOOLEAN);
            }
            $steps[$step] = $settings;
        }

        return ['enabled' => filter_var($input['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN), 'steps' => $steps];
    }

    /**
     * The saved forms, keeping only what the catalog still has (a save drops names that are gone).
     *
     * @return array<string, mixed>
     */
    private function savedForms(): array
    {
        $forms = [];
        foreach ($this->catalog->forms() as $key => $form) {
            $saved = $this->settings->form($key);
            if ($saved === null) {
                continue;
            }
            $kept = array_key_exists('enabled', $saved) ? ['enabled' => (bool) $saved['enabled']] : [];
            foreach ((array) ($saved['steps'] ?? []) as $step => $settings) {
                $step = (string) $step;
                if (! $form->hasStep($step) || ! is_array($settings)) {
                    continue;
                }
                $kept['steps'][$step] = [];
                if (isset($settings['event'])) {
                    $kept['steps'][$step]['event'] = $settings['event'];
                }
                foreach ((array) ($settings['params'] ?? []) as $name => $send) {
                    if (isset($form->params[$name])) {
                        $kept['steps'][$step]['params'][$name] = (bool) $send;
                    }
                }
            }
            $forms[$key] = $kept;
        }

        return $forms;
    }

    /**
     * @param  array<mixed>  $input
     * @return array<string, string>
     */
    private function containersFrom(array $input): array
    {
        $ids = [];
        foreach ($this->containers as $name => $container) {
            if ($container['follows'] === null) {
                $ids[$name] = is_string($input[$name] ?? null) ? trim($input[$name]) : '';
            }
        }
        foreach ($this->containers as $name => $container) {
            if ($container['follows'] !== null) {
                $ids[$name] = filter_var($input[$name] ?? false, FILTER_VALIDATE_BOOLEAN) ? ($ids[$container['follows']] ?? '') : '';
            }
        }

        return $ids;
    }

    /**
     * @return array<string, string>
     */
    private function savedContainers(): array
    {
        $ids = [];
        foreach (array_keys($this->containers) as $name) {
            $id = $this->settings->container($name);
            if ($id !== null) {
                $ids[$name] = $id;
            }
        }

        return $ids;
    }
}
