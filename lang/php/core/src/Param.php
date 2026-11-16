<?php

declare(strict_types=1);

namespace Otaqq\FormDatalayer;

/**
 * A parameter a form may send, and how its value is found (spec/catalog.md#parameters).
 */
final class Param
{
    /**
     * @param  array{table: string, key: string, column: string}|null  $lookup
     */
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly string $from,
        public readonly ?array $lookup = null,
        public readonly ?string $join = ',',
        public readonly ?int $maxLength = 100,
        public readonly bool $default = false,
    ) {}
}
