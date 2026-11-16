<?php

declare(strict_types=1);

namespace Otaqq\FormDatalayer;

use InvalidArgumentException;
use JsonException;
use Otaqq\FormDatalayer\Exception\InvalidCatalog;

/**
 * The forms and pages an app can measure, and the parameters each may send (spec/catalog.md).
 * Loading checks the schema and the other rules, and throws InvalidCatalog when they fail.
 */
final class Catalog
{
    private const FORM_FIELDS = ['label', 'enabled', 'steps', 'params'];

    private const PARAM_FIELDS = ['label', 'from', 'lookup', 'join', 'max_length', 'default'];

    /**
     * @param  array<string, Form>  $forms
     * @param  array<string, Page>  $pages
     */
    private function __construct(private readonly array $forms, private readonly array $pages) {}

    public static function fromFile(string $path): self
    {
        $json = @file_get_contents($path);
        if ($json === false) {
            throw new InvalidCatalog("Cannot read the catalog {$path}");
        }

        return self::fromJson($json);
    }

    public static function fromJson(string $json): self
    {
        try {
            $data = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidCatalog('The catalog is not JSON: '.$e->getMessage(), 0, $e);
        }
        if (! is_array($data)) {
            throw new InvalidCatalog('The catalog must be an object');
        }

        return self::fromArray($data);
    }

    /**
     * @param  array<mixed>  $data  The decoded JSON
     */
    public static function fromArray(array $data): self
    {
        self::fields($data, ['$schema', 'version', 'forms'], 'catalog');
        if (($data['version'] ?? null) !== 1) {
            throw new InvalidCatalog('catalog.version must be 1');
        }
        $formsData = $data['forms'] ?? null;
        if (! self::isMap($formsData)) {
            throw new InvalidCatalog('catalog.forms must be an object');
        }

        $forms = [];
        foreach ($formsData as $key => $formData) {
            $key = (string) $key;
            if (! Names::isFormKey($key)) {
                throw new InvalidCatalog("Invalid form key: {$key}");
            }
            $forms[$key] = self::parseForm($key, $formData);
        }

        return self::checked($forms);
    }

    /**
     * The catalog with the pages the framework's routes declare (spec/push.md#pages-from-routes).
     * A form only routes name is added with its key as label and no parameters.
     *
     * @param  list<Page>  $pages
     */
    public function withPages(array $pages): self
    {
        $forms = $this->forms;
        $byForm = [];
        foreach ($pages as $page) {
            if (! Names::isFormKey($page->form)) {
                throw new InvalidCatalog("Invalid form key on the page {$page->id}: {$page->form}");
            }
            if (! in_array($page->step, Names::PAGE_STEPS, true)) {
                throw new InvalidCatalog("Invalid step on the page {$page->id}: {$page->step} (input, confirm or complete)");
            }
            if ($page->mode !== null && ! in_array($page->mode, Names::MODES, true)) {
                throw new InvalidCatalog("Invalid mode on the page {$page->id}: {$page->mode} (create or edit)");
            }
            $byForm[$page->form][$page->step][] = $page;
        }
        foreach ($byForm as $key => $steps) {
            $forms[$key] = ($forms[$key] ?? new Form($key, $key, true, [], []))->withPages($steps);
        }

        return self::checked($forms);
    }

    /**
     * @param  array<string, Form>  $forms
     */
    private static function checked(array $forms): self
    {
        $pages = [];
        foreach ($forms as $key => $form) {
            foreach (Names::FORM_STEPS as $step) {
                if (! Names::isEventName(Names::defaultEventName($key, $step))) {
                    throw new InvalidCatalog("The default event name of {$key}.{$step} is longer than 40 characters or starts with google_, ga_ or firebase_; change the key");
                }
            }
            foreach ($form->steps as $stepPages) {
                foreach ($stepPages as $page) {
                    if (isset($pages[$page->id])) {
                        throw new InvalidCatalog("The page {$page->id} appears twice");
                    }
                    $pages[$page->id] = $page;
                }
            }
        }

        return new self($forms, $pages);
    }

    /**
     * @return array<string, Form>
     */
    public function forms(): array
    {
        return $this->forms;
    }

    public function has(string $key): bool
    {
        return isset($this->forms[$key]);
    }

    /**
     * @throws InvalidArgumentException when the form is not in the catalog
     */
    public function form(string $key): Form
    {
        return $this->forms[$key] ?? throw new InvalidArgumentException("The form {$key} is not in the catalog");
    }

    /**
     * The form, step and mode of a page id, or null when the page is not in the catalog.
     */
    public function page(string $id): ?Page
    {
        return $this->pages[$id] ?? null;
    }

    private static function parseForm(string $key, mixed $data): Form
    {
        $at = "forms.{$key}";
        if (! self::isMap($data)) {
            throw new InvalidCatalog("{$at} must be an object");
        }
        self::fields($data, self::FORM_FIELDS, $at);
        $label = self::label($data['label'] ?? null, $at);
        $enabled = array_key_exists('enabled', $data) ? $data['enabled'] : true;
        if (! is_bool($enabled)) {
            throw new InvalidCatalog("{$at}.enabled must be true or false");
        }

        $stepsData = $data['steps'] ?? [];
        if (! self::isMap($stepsData)) {
            throw new InvalidCatalog("{$at}.steps must be an object");
        }
        if (array_diff(array_map('strval', array_keys($stepsData)), Names::PAGE_STEPS) !== []) {
            throw new InvalidCatalog("{$at}.steps may have input, confirm and complete only");
        }
        $steps = [];
        foreach (Names::PAGE_STEPS as $step) {
            if (array_key_exists($step, $stepsData)) {
                $steps[$step] = self::parsePages($key, $step, $stepsData[$step], "{$at}.steps.{$step}");
            }
        }

        $paramsData = $data['params'] ?? [];
        if (! self::isMap($paramsData)) {
            throw new InvalidCatalog("{$at}.params must be an object");
        }
        $params = [];
        foreach ($paramsData as $name => $paramData) {
            $name = (string) $name;
            if (! Names::isParamName($name)) {
                throw new InvalidCatalog("Invalid parameter name: {$at}.params.{$name}");
            }
            $params[$name] = self::parseParam($name, $paramData, "{$at}.params.{$name}");
        }

        return new Form($key, $label, $enabled, $steps, $params);
    }

    /**
     * @return list<Page>
     */
    private static function parsePages(string $form, string $step, mixed $data, string $at): array
    {
        if (! is_array($data) || ! array_is_list($data)) {
            throw new InvalidCatalog("{$at} must be a list of pages");
        }
        $pages = [];
        foreach ($data as $i => $pageData) {
            if (! self::isMap($pageData)) {
                throw new InvalidCatalog("{$at}.{$i} must be an object");
            }
            self::fields($pageData, ['page', 'mode'], "{$at}.{$i}");
            $id = $pageData['page'] ?? null;
            if (! is_string($id) || $id === '' || mb_strlen($id, 'UTF-8') > 200) {
                throw new InvalidCatalog("{$at}.{$i}.page must be a non-empty string");
            }
            $mode = $pageData['mode'] ?? null;
            if (array_key_exists('mode', $pageData) && ! in_array($mode, Names::MODES, true)) {
                throw new InvalidCatalog("{$at}.{$i}.mode must be create or edit");
            }
            $pages[] = new Page($id, $form, $step, $mode);
        }

        return $pages;
    }

    private static function parseParam(string $name, mixed $data, string $at): Param
    {
        if (! self::isMap($data)) {
            throw new InvalidCatalog("{$at} must be an object");
        }
        self::fields($data, self::PARAM_FIELDS, $at);
        $label = self::label($data['label'] ?? null, $at);
        $from = $data['from'] ?? null;
        if (! is_string($from) || preg_match('/^[A-Za-z0-9_]+(?:\.(?:[A-Za-z0-9_]+|\*))*$/D', $from) !== 1) {
            throw new InvalidCatalog("{$at}.from must be a path such as posting.locations.*.prefecture_id");
        }

        $lookup = $data['lookup'] ?? null;
        if (array_key_exists('lookup', $data)) {
            if (! self::isMap($lookup)) {
                throw new InvalidCatalog("{$at}.lookup must be an object");
            }
            self::fields($lookup, ['table', 'key', 'column'], "{$at}.lookup");
            $identifier = '[A-Za-z_][A-Za-z0-9_]*';
            if (! is_string($lookup['table'] ?? null) || preg_match("/^{$identifier}(?:\\.{$identifier})?$/D", $lookup['table']) !== 1
                || ! is_string($lookup['key'] ?? null) || preg_match("/^{$identifier}$/D", $lookup['key']) !== 1
                || ! is_string($lookup['column'] ?? null) || preg_match("/^{$identifier}$/D", $lookup['column']) !== 1) {
                throw new InvalidCatalog("{$at}.lookup needs table, key and column, as identifiers");
            }
            $lookup = ['table' => $lookup['table'], 'key' => $lookup['key'], 'column' => $lookup['column']];
        }

        $join = array_key_exists('join', $data) ? $data['join'] : ',';
        if ($join !== null && ! is_string($join)) {
            throw new InvalidCatalog("{$at}.join must be a string or null");
        }
        $maxLength = array_key_exists('max_length', $data) ? $data['max_length'] : 100;
        if ($maxLength !== null && (! is_int($maxLength) || $maxLength < 1)) {
            throw new InvalidCatalog("{$at}.max_length must be a positive whole number or null");
        }
        $default = array_key_exists('default', $data) ? $data['default'] : false;
        if (! is_bool($default)) {
            throw new InvalidCatalog("{$at}.default must be true or false");
        }

        return new Param($name, $label, $from, $lookup, $join, $maxLength, $default);
    }

    private static function label(mixed $label, string $at): string
    {
        if (! is_string($label) || $label === '') {
            throw new InvalidCatalog("{$at}.label must be a non-empty string");
        }

        return $label;
    }

    /**
     * @param  array<mixed>  $data
     * @param  list<string>  $allowed
     */
    private static function fields(array $data, array $allowed, string $at): void
    {
        $unknown = array_diff(array_map('strval', array_keys($data)), $allowed);
        if ($unknown !== []) {
            throw new InvalidCatalog("Unknown field in {$at}: ".implode(', ', $unknown));
        }
    }

    /**
     * A JSON object as decoded by json_decode(…, true): an array that is not a non-empty list.
     */
    private static function isMap(mixed $value): bool
    {
        return is_array($value) && ($value === [] || ! array_is_list($value));
    }
}
