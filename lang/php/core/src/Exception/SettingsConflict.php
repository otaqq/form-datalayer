<?php

declare(strict_types=1);

namespace Otaqq\FormDatalayer\Exception;

use Otaqq\FormDatalayer\Settings;
use RuntimeException;

/**
 * Someone else saved first: the version is not the one the screen was opened with (spec/settings.md#saving).
 */
final class SettingsConflict extends RuntimeException
{
    public function __construct(public readonly Settings $current)
    {
        parent::__construct("The form-datalayer settings were saved by someone else (now version {$current->version}).");
    }
}
