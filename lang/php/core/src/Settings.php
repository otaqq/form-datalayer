<?php

declare(strict_types=1);

namespace Otaqq\FormDatalayer;

use JsonException;

/**
 * Which forms are measured, their event names and chosen parameters (spec/settings.md).
 * Reading never throws: a missing file is empty settings, and a file that is not JSON or fails the schema
 * is broken (`$broken`), which stops every push.
 */
final class Settings
{
    private const STEPS = [...Names::FORM_STEPS, Names::PAGE_STEP];

    /**
     * @param  array<string, array{enabled?: bool, steps?: array<string, array{event?: string|null, params?: array<string, bool>}>}>  $forms
     */
    /**
     * @param  array<string, string>  $containers  Container name → GTM container id ('' for none)
     */
    private function __construct(
        public readonly int $version,
        private readonly array $forms,
        public readonly ?string $updatedAt = null,
        public readonly ?string $updatedBy = null,
        public readonly ?string $broken = null,
        private readonly array $containers = [],
    ) {}

    /**
     * No settings yet (`version: 0`): everything follows the defaults.
     */
    public static function empty(): self
    {
        return new self(0, []);
    }

    /**
     * Settings that cannot be trusted. Nothing is pushed; `version` is 0 so that a save can replace them.
     */
    public static function broken(string $reason): self
    {
        return new self(0, [], broken: $reason);
    }

    public static function fromFile(string $path): self
    {
        if (! is_file($path)) {
            return self::empty();
        }
        $json = @file_get_contents($path);

        return $json === false ? self::broken("cannot read {$path}") : self::fromJson($json);
    }

    public static function fromJson(string $json): self
    {
        try {
            $data = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return self::broken('not JSON: '.$e->getMessage());
        }

        return is_array($data) ? self::fromArray($data) : self::broken('not an object');
    }

    /**
     * @param  array<mixed>  $data  The decoded JSON
     */
    public static function fromArray(array $data): self
    {
        $errors = self::validate($data);
        if ($errors !== []) {
            return self::broken(implode('; ', $errors));
        }

        return new self($data['version'], $data['forms'] ?? [], $data['updated_at'] ?? null, $data['updated_by'] ?? null, containers: $data['containers'] ?? []);
    }

    /**
     * The schema errors of decoded settings (spec/schema/settings.schema.json). Empty when they pass.
     *
     * @param  array<mixed>  $data
     * @return list<string>
     */
    public static function validate(array $data): array
    {
        $errors = [];
        self::fields($data, ['version', 'updated_at', 'updated_by', 'containers', 'forms'], 'settings', $errors);
        if (! is_int($data['version'] ?? null) || $data['version'] < 0) {
            $errors[] = 'version must be a whole number, 0 or more';
        }
        foreach (['updated_at', 'updated_by'] as $field) {
            if (array_key_exists($field, $data) && ! is_string($data[$field])) {
                $errors[] = "{$field} must be a string";
            }
        }
        $containers = $data['containers'] ?? [];
        if (! self::isMap($containers)) {
            $errors[] = 'containers must be an object';
        } else {
            foreach ($containers as $name => $id) {
                if (! Names::isContainerName((string) $name) || ! is_string($id) || ($id !== '' && ! Output::isContainerId($id))) {
                    $errors[] = "containers.{$name} must be a GTM container id (GTM-…) or empty";
                }
            }
        }
        $forms = $data['forms'] ?? [];
        if (! self::isMap($forms)) {
            return [...$errors, 'forms must be an object'];
        }
        foreach ($forms as $key => $form) {
            $at = "forms.{$key}";
            if (! Names::isFormKey((string) $key)) {
                $errors[] = "invalid form key: {$key}";
            }
            if (! self::isMap($form)) {
                $errors[] = "{$at} must be an object";

                continue;
            }
            self::fields($form, ['enabled', 'steps'], $at, $errors);
            if (array_key_exists('enabled', $form) && ! is_bool($form['enabled'])) {
                $errors[] = "{$at}.enabled must be true or false";
            }
            $steps = $form['steps'] ?? [];
            if (! self::isMap($steps)) {
                $errors[] = "{$at}.steps must be an object";

                continue;
            }
            foreach ($steps as $step => $settings) {
                $errors = [...$errors, ...self::validateStep((string) $step, $settings, "{$at}.steps.{$step}")];
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private static function validateStep(string $step, mixed $settings, string $at): array
    {
        $errors = [];
        if (! in_array($step, self::STEPS, true)) {
            $errors[] = "{$at}: unknown step";
        }
        if (! self::isMap($settings)) {
            return [...$errors, "{$at} must be an object"];
        }
        self::fields($settings, ['event', 'params'], $at, $errors);
        $event = $settings['event'] ?? null;
        if ($event !== null && ! Names::isEventName($event)) {
            $errors[] = "{$at}.event is not a valid event name";
        }
        $params = $settings['params'] ?? [];
        if (! self::isMap($params)) {
            return [...$errors, "{$at}.params must be an object"];
        }
        foreach ($params as $name => $send) {
            if (! Names::isName((string) $name) || ! is_bool($send)) {
                $errors[] = "{$at}.params.{$name} must be a parameter name set to true or false";
            }
        }

        return $errors;
    }

    public function isBroken(): bool
    {
        return $this->broken !== null;
    }

    /**
     * The container id saved for a container name: a GTM id, '' for none, or null when not saved
     * (the app's default then applies).
     */
    public function container(string $name): ?string
    {
        return $this->containers[$name] ?? null;
    }

    /**
     * Whether the settings measure the form (true unless turned off).
     */
    public function formEnabled(string $form): bool
    {
        return $this->forms[$form]['enabled'] ?? true;
    }

    /**
     * The event name chosen for a step, or null for the default name.
     */
    public function event(string $form, string $step): ?string
    {
        return $this->forms[$form]['steps'][$step]['event'] ?? null;
    }

    /**
     * Whether a step sends a parameter: true or false as saved, or null when the settings do not mention it
     * (it then follows its `default`).
     */
    public function param(string $form, string $step, string $name): ?bool
    {
        return $this->forms[$form]['steps'][$step]['params'][$name] ?? null;
    }

    /**
     * Whether a parameter is new for a step: the step was saved with its parameters, but not this one
     * (it was added to the catalog after the last save).
     */
    public function isNewParam(string $form, string $step, string $name): bool
    {
        $params = $this->forms[$form]['steps'][$step]['params'] ?? null;

        return is_array($params) && ! array_key_exists($name, $params);
    }

    /**
     * The names in the settings that the catalog no longer has (spec/settings.md#settings-and-the-catalog-over-time).
     *
     * @param  list<string>|null  $containers  The container names the app declares; null to skip containers
     * @return list<string>  `unknown container: <name>`, `unknown form: <key>`, `unknown step: <key>.<step>`, `unknown param: <key>.<step>.<name>`
     */
    public function stale(Catalog $catalog, ?array $containers = null): array
    {
        $stale = [];
        foreach ($containers === null ? [] : array_keys($this->containers) as $name) {
            if (! in_array($name, $containers, true)) {
                $stale[] = "unknown container: {$name}";
            }
        }
        foreach ($this->forms as $key => $form) {
            if (! $catalog->has($key)) {
                $stale[] = "unknown form: {$key}";

                continue;
            }
            $catalogForm = $catalog->form($key);
            foreach ($form['steps'] ?? [] as $step => $settings) {
                if (! $catalogForm->hasStep($step)) {
                    $stale[] = "unknown step: {$key}.{$step}";

                    continue;
                }
                foreach (array_keys($settings['params'] ?? []) as $name) {
                    if (! isset($catalogForm->params[$name])) {
                        $stale[] = "unknown param: {$key}.{$step}.{$name}";
                    }
                }
            }
        }

        return $stale;
    }

    /**
     * The settings as saved in the file.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'version' => $this->version,
            'updated_at' => $this->updatedAt,
            'updated_by' => $this->updatedBy,
            'containers' => $this->containers === [] ? null : $this->containers,
            'forms' => $this->forms,
        ], fn ($value) => $value !== null);
    }

    /**
     * @param  array<mixed>  $data
     * @param  list<string>  $allowed
     * @param  list<string>  $errors
     */
    private static function fields(array $data, array $allowed, string $at, array &$errors): void
    {
        $unknown = array_diff(array_map('strval', array_keys($data)), $allowed);
        if ($unknown !== []) {
            $errors[] = "unknown field in {$at}: ".implode(', ', $unknown);
        }
    }

    private static function isMap(mixed $value): bool
    {
        return is_array($value) && ($value === [] || ! array_is_list($value));
    }
}
