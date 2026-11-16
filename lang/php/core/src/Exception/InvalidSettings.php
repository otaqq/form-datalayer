<?php

declare(strict_types=1);

namespace Otaqq\FormDatalayer\Exception;

use InvalidArgumentException;

/**
 * Settings that cannot be saved: they fail the schema or name something the catalog does not have.
 */
final class InvalidSettings extends InvalidArgumentException
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Invalid form-datalayer settings: '.implode('; ', $errors));
    }
}
