<?php

declare(strict_types=1);

namespace Otaqq\FormDatalayer;

/**
 * A Lookup over rows held in memory: table → list of rows.
 */
final class ArrayLookup implements Lookup
{
    /**
     * @param  array<string, list<array<string, mixed>>>  $tables
     */
    public function __construct(private readonly array $tables = []) {}

    public function find(string $table, string $key, string $column, array $values): array
    {
        $wanted = array_map(Value::text(...), $values);
        $found = [];
        foreach ($this->tables[$table] ?? [] as $row) {
            if (! array_key_exists($key, $row) || ! array_key_exists($column, $row) || ! is_scalar($row[$key]) || ! is_scalar($row[$column])) {
                continue;
            }
            $id = Value::text($row[$key]);
            if (in_array($id, $wanted, true) && ! isset($found[$id])) {
                $found[$id] = $row[$column];
            }
        }

        return $found;
    }
}
