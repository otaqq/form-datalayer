<?php

declare(strict_types=1);

namespace Otaqq\FormDatalayer;

/**
 * A form or a page of the catalog (spec/catalog.md#fields).
 */
final class Form
{
    /**
     * @param  array<string, list<Page>>  $steps  Step → pages, as listed in the catalog (`error` is not listed)
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
     * A page (a `view` step) rather than a form.
     */
    public function isPage(): bool
    {
        return isset($this->steps[Names::PAGE_STEP]);
    }

    /**
     * Every step the form has, `error` included for forms: input, confirm, complete, error, or view.
     *
     * @return list<string>
     */
    public function stepNames(): array
    {
        if ($this->isPage()) {
            return [Names::PAGE_STEP];
        }

        return array_values(array_filter(Names::FORM_STEPS, fn (string $step) => $step === 'error' || isset($this->steps[$step])));
    }

    public function hasStep(string $step): bool
    {
        return in_array($step, $this->stepNames(), true);
    }
}
