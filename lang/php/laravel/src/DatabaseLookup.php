<?php

declare(strict_types=1);

namespace Otaqq\FormDatalayer\Laravel;

use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\QueryException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Otaqq\FormDatalayer\Lookup;
use Otaqq\FormDatalayer\Value;

/**
 * Looks ids up with the query builder: one query per table, key and column.
 * A query that fails (a value of the wrong type for the key column, on PostgreSQL) finds no rows, with a warning.
 */
final class DatabaseLookup implements Lookup
{
    public function __construct(
        private readonly ConnectionResolverInterface $db,
        private readonly ?string $connection = null,
        private readonly LoggerInterface $logger = new NullLogger,
    ) {}

    public function find(string $table, string $key, string $column, array $values): array
    {
        $found = [];
        try {
            $rows = $this->db->connection($this->connection)->table($table)->whereIn($key, $values)->get([$key, $column]);
        } catch (QueryException $e) {
            $this->logger->warning("form-datalayer: lookup in {$table} failed, nothing found: {$e->getMessage()}");

            return [];
        }
        foreach ($rows as $row) {
            $id = $row->{$key};
            $name = $row->{$column};
            if (is_scalar($id) && is_scalar($name)) {
                $found[Value::text($id)] ??= $name;
            }
        }

        return $found;
    }
}
