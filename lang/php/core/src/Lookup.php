<?php

declare(strict_types=1);

namespace Otaqq\FormDatalayer;

/**
 * Turns ids into names for parameters with a `lookup` (spec/push.md#values). Called once per table and push.
 */
interface Lookup
{
    /**
     * @param  list<string|int|float|bool>  $values
     * @return array<string, string|int|float|bool>  `column` of each row found, keyed by its `key` as a string
     */
    public function find(string $table, string $key, string $column, array $values): array;
}
