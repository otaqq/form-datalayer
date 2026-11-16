<?php

declare(strict_types=1);

namespace Otaqq\FormDatalayer;

/**
 * Where a page belongs: its form, its step, and the mode when the catalog gives one.
 */
final class Page
{
    public function __construct(
        public readonly string $id,
        public readonly string $form,
        public readonly string $step,
        public readonly ?string $mode = null,
    ) {}
}
