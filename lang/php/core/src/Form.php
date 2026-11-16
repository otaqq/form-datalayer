<?php

declare(strict_types=1);

namespace Otaqq\FormDatalayer;

/**
 * A form of the catalog, with its pages from the catalog and the routes (spec/catalog.md#fields).
 */
final class Form
{
    /**
     * @param  array<string, list<Page>>  $steps  Step → pages (`input`, `confirm`, `complete`; `error` is not listed)
     * @param  array<string, Param>  $params  In catalog order
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly bool $enabled,
        public readonly array $steps,
        public readonly array $params,
    ) {}

    /**
     * The group of the form (the first word of its key), for the tabs of the settings screen.
     */
    public function group(): string
    {
        return Names::group($this->key);
    }

    /**
     * The steps the form shows, in order: those with pages, and `error` when it has `input`.
     *
     * @return list<string>
     */
    public function stepNames(): array
    {
        return array_values(array_filter(
            Names::FORM_STEPS,
            fn (string $step) => isset($this->steps[$step]) || ($step === 'error' && isset($this->steps['input'])),
        ));
    }

    /**
     * Whether the step is a form step. Any form may push any of them (the app may push a step itself).
     */
    public function hasStep(string $step): bool
    {
        return in_array($step, Names::FORM_STEPS, true);
    }

    /**
     * The same form with more pages.
     *
     * @param  array<string, list<Page>>  $steps
     */
    public function withPages(array $steps): self
    {
        $merged = $this->steps;
        foreach ($steps as $step => $pages) {
            $merged[$step] = [...($merged[$step] ?? []), ...$pages];
        }
        $ordered = [];
        foreach (Names::PAGE_STEPS as $step) {
            if (isset($merged[$step])) {
                $ordered[$step] = $merged[$step];
            }
        }

        return new self($this->key, $this->label, $this->enabled, $ordered, $this->params);
    }
}
