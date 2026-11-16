<?php

declare(strict_types=1);

namespace Otaqq\FormDatalayer;

/**
 * Finds a parameter's value in the context (spec/push.md#values, steps 1 and 2).
 */
final class Value
{
    /**
     * Follows `from` through the context. Returns null for no value, a plain value, or a list of plain values
     * (when the path has `*`). Maps and lists where a plain value is expected are dropped, with `$warn` called.
     *
     * @param  callable(string): void|null  $warn
     * @return array{list: bool, values: list<string|int|float|bool>}|null
     */
    public static function find(mixed $context, string $from, ?callable $warn = null): ?array
    {
        $current = [$context];
        $isList = false;
        foreach (explode('.', $from) as $segment) {
            $next = [];
            foreach ($current as $node) {
                if ($segment === '*') {
                    if (is_array($node) && array_is_list($node)) {
                        array_push($next, ...$node);
                    }
                } elseif (is_array($node) && ($node === [] || ! array_is_list($node)) && array_key_exists($segment, $node)) {
                    $next[] = $node[$segment];
                }
            }
            $isList = $isList || $segment === '*';
            $current = $next;
        }

        $values = [];
        foreach ($current as $value) {
            if ($value === null) {
                continue;
            }
            if (! is_scalar($value) || (is_float($value) && ! is_finite($value)) || (is_string($value) && ! mb_check_encoding($value, 'UTF-8'))) {
                if ($warn !== null) {
                    $warn("not a plain value: {$from}");
                }

                continue;
            }
            $values[] = $value;
        }

        if ($values === [] && ! $isList) {
            return null;
        }

        return ['list' => $isList, 'values' => $values];
    }

    /**
     * A plain value as text: `true` / `false`, numbers in their shortest form.
     */
    public static function text(string|int|float|bool $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            is_string($value) => $value,
            default => (string) json_encode($value),
        };
    }
}
