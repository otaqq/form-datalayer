<?php

declare(strict_types=1);

namespace Otaqq\FormDatalayer;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Builds the pushes of one request from the catalog, the settings and the context (spec/push.md#building-the-push).
 * Stale names in the settings are logged once as warnings, and broken settings once as an error.
 */
final class Pusher
{
    private bool $reported = false;

    public function __construct(
        private readonly Catalog $catalog,
        private readonly Settings $settings,
        private readonly Lookup $lookup = new ArrayLookup,
        private readonly LoggerInterface $logger = new NullLogger,
    ) {}

    /**
     * The push of a step, or null when nothing is pushed.
     *
     * @param  array<mixed>  $context
     * @return array<string, mixed>|null
     *
     * @throws InvalidArgumentException when the form or the step is not in the catalog, or the mode is unknown
     */
    public function push(string $form, string $step, array $context = [], ?string $mode = null): ?array
    {
        $catalogForm = $this->catalog->form($form);
        if (! $catalogForm->hasStep($step)) {
            throw new InvalidArgumentException("The form {$form} has no step {$step}");
        }
        if ($mode !== null && ! in_array($mode, Names::MODES, true)) {
            throw new InvalidArgumentException("Unknown mode {$mode}: create or edit");
        }
        $this->report();

        if (! $catalogForm->enabled || $this->settings->isBroken() || ! $this->settings->formEnabled($form)) {
            return null;
        }

        $push = [
            'event' => $this->settings->event($form, $step) ?? Names::defaultEventName($form, $step),
            'form' => $form,
            'step' => $step,
        ];
        if ($mode !== null) {
            $push['mode'] = $mode;
        }

        $warn = fn (string $message) => $this->logger->warning("form-datalayer: {$message}", ['form' => $form, 'step' => $step]);
        $found = [];
        foreach ($catalogForm->params as $name => $param) {
            if ($this->settings->param($form, $step, $name) ?? $param->default) {
                $value = Value::find($context, $param->from, $warn);
                if ($value !== null) {
                    $found[$name] = $value;
                }
            }
        }

        $names = $this->lookUp($catalogForm, $found);
        foreach ($found as $name => $value) {
            $param = $catalogForm->params[$name];
            $values = $value['values'];
            if ($param->lookup !== null) {
                $table = $names[self::lookupKey($param)];
                $values = array_values(array_filter(
                    array_map(fn ($v) => $table[Value::text($v)] ?? null, $values),
                    fn ($v) => $v !== null,
                ));
            }
            $result = self::finish($values, $value['list'], $param);
            if ($result !== null) {
                $push[$name] = $result;
            }
        }

        return $push;
    }

    /**
     * Looks every value up with one call per table, key and column.
     *
     * @param  array<string, array{list: bool, values: list<string|int|float|bool>}>  $found
     * @return array<string, array<string, string|int|float|bool>>
     */
    private function lookUp(Form $form, array $found): array
    {
        $wanted = [];
        foreach ($found as $name => $value) {
            $param = $form->params[$name];
            if ($param->lookup !== null) {
                $key = self::lookupKey($param);
                $wanted[$key] ??= ['lookup' => $param->lookup, 'values' => []];
                foreach ($value['values'] as $v) {
                    if (! in_array($v, $wanted[$key]['values'], true)) {
                        $wanted[$key]['values'][] = $v;
                    }
                }
            }
        }

        $names = [];
        foreach ($wanted as $key => ['lookup' => $lookup, 'values' => $values]) {
            $names[$key] = $values === [] ? [] : $this->lookup->find($lookup['table'], $lookup['key'], $lookup['column'], $values);
        }

        return $names;
    }

    private static function lookupKey(Param $param): string
    {
        return implode("\0", $param->lookup ?? []);
    }

    /**
     * Steps 4 and 5 of spec/push.md#values: drop repeats, join lists, cut long strings.
     *
     * @param  list<string|int|float|bool>  $values
     */
    private static function finish(array $values, bool $isList, Param $param): string|int|float|bool|array|null
    {
        if (! $isList) {
            return $values === [] ? null : self::cut($values[0], $param->maxLength);
        }

        $unique = [];
        foreach ($values as $value) {
            if (! in_array($value, $unique, true)) {
                $unique[] = $value;
            }
        }
        if ($unique === []) {
            return null;
        }
        if ($param->join === null) {
            return array_map(fn ($v) => self::cut($v, $param->maxLength), $unique);
        }

        return self::cut(implode($param->join, array_map(Value::text(...), $unique)), $param->maxLength);
    }

    private static function cut(string|int|float|bool $value, ?int $maxLength): string|int|float|bool
    {
        return is_string($value) && $maxLength !== null && mb_strlen($value, 'UTF-8') > $maxLength
            ? mb_substr($value, 0, $maxLength, 'UTF-8')
            : $value;
    }

    private function report(): void
    {
        if ($this->reported) {
            return;
        }
        $this->reported = true;
        if ($this->settings->isBroken()) {
            $this->logger->error("form-datalayer: broken settings, nothing is pushed: {$this->settings->broken}");

            return;
        }
        foreach ($this->settings->stale($this->catalog) as $stale) {
            $this->logger->warning("form-datalayer: {$stale}");
        }
    }
}
