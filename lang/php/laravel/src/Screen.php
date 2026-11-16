<?php

declare(strict_types=1);

namespace Otaqq\FormDatalayer\Laravel;

use Otaqq\FormDatalayer\Catalog;
use Otaqq\FormDatalayer\Form;
use Otaqq\FormDatalayer\Names;
use Otaqq\FormDatalayer\Settings;

/**
 * What the settings screen shows and saves (spec/admin.md#screen).
 */
final class Screen
{
    /**
     * @param  array<string, array{label: string, default: string}>  $containers  The containers the app declares
     */
    public function __construct(private readonly Catalog $catalog, private readonly Settings $settings, private readonly array $containers = []) {}

    /**
     * The container ids as the screen shows them: saved, or the config default until saved.
     *
     * @return list<array{name: string, label: string, id: string}>
     */
    public function containers(): array
    {
        $rows = [];
        foreach ($this->containers as $name => $container) {
            $rows[] = ['name' => $name, 'label' => $container['label'], 'id' => $this->settings->container($name) ?? $container['default']];
        }

        return $rows;
    }

    /**
     * The container ids to save from the submitted screen: every declared container, '' for none.
     *
     * @param  array<mixed>  $input  `containers.<name>`
     * @return array<string, string>
     */
    public function containersToSave(array $input): array
    {
        $ids = [];
        foreach (array_keys($this->containers) as $name) {
            $ids[$name] = is_string($input[$name] ?? null) ? trim($input[$name]) : '';
        }

        return $ids;
    }

    /**
     * Every form of the catalog, with its steps and parameters as the screen shows them.
     *
     * @return list<array{key: string, label: string, locked: bool, enabled: bool, steps: list<array{step: string, event: string, default_event: string, params: list<array{name: string, label: string, checked: bool, new: bool}>}>}>
     */
    public function forms(): array
    {
        $rows = [];
        foreach ($this->catalog->forms() as $key => $form) {
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
            $rows[] = [
                'key' => $key,
                'label' => $form->label,
                'locked' => ! $form->enabled,
                'enabled' => $form->enabled && $this->settings->formEnabled($key),
                'steps' => $steps,
            ];
        }

        return $rows;
    }

    /**
     * The names in the saved settings the catalog no longer has (`unknown form: …`, …).
     *
     * @return list<string>
     */
    public function removed(): array
    {
        return $this->settings->isBroken() ? [] : $this->settings->stale($this->catalog, array_keys($this->containers));
    }

    /**
     * The settings to save from the submitted screen: every form, step and parameter of the catalog.
     * Forms the catalog turns off keep what was saved.
     *
     * @param  array<mixed>  $input  `forms.<key>.enabled`, `forms.<key>.steps.<step>.event`, `forms.<key>.steps.<step>.params.<name>`
     * @return array<string, mixed>
     */
    public function toSave(array $input): array
    {
        $forms = [];
        foreach ($this->catalog->forms() as $key => $form) {
            $forms[$key] = $form->enabled ? $this->fromInput($form, $input[$key] ?? []) : $this->fromSaved($form);
        }

        return $forms;
    }

    /**
     * @return array<string, mixed>
     */
    private function fromInput(Form $form, mixed $input): array
    {
        $input = is_array($input) ? $input : [];
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
     * @return array<string, mixed>
     */
    private function fromSaved(Form $form): array
    {
        $steps = [];
        foreach ($form->stepNames() as $step) {
            $settings = [];
            $event = $this->settings->event($form->key, $step);
            if ($event !== null) {
                $settings['event'] = $event;
            }
            foreach (array_keys($form->params) as $name) {
                $send = $this->settings->param($form->key, $step, $name);
                if ($send !== null) {
                    $settings['params'][$name] = $send;
                }
            }
            if ($settings !== []) {
                $steps[$step] = $settings;
            }
        }
        $saved = ['enabled' => $this->settings->formEnabled($form->key)];

        return $steps === [] ? $saved : [...$saved, 'steps' => $steps];
    }
}
