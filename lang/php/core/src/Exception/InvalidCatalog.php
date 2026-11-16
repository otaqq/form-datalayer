<?php

declare(strict_types=1);

namespace Otaqq\FormDatalayer\Exception;

use InvalidArgumentException;

/**
 * The catalog fails its schema or its checks (spec/catalog.md#checks). A programming error: fix the catalog.
 */
final class InvalidCatalog extends InvalidArgumentException {}
