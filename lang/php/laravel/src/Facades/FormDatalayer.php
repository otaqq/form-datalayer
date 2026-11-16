<?php

declare(strict_types=1);

namespace Otaqq\FormDatalayer\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Otaqq\FormDatalayer\Laravel\FormDatalayer with(array $context)
 * @method static void push(string $form, string $step, array $context = [], ?string $mode = null)
 * @method static void flash(string $form, string $step, array $context = [], ?string $mode = null)
 * @method static list<array<string, mixed>> pushes()
 * @method static string head(string $container = 'default')
 * @method static string body(string $container = 'default')
 * @method static string|null mode(?string $mode = null)
 * @method static \Otaqq\FormDatalayer\Catalog catalog()
 * @method static \Otaqq\FormDatalayer\Settings settings()
 * @method static array<string, array{label: string, default: string, follows: string|null}> containers()
 *
 * @see \Otaqq\FormDatalayer\Laravel\FormDatalayer
 */
final class FormDatalayer extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Otaqq\FormDatalayer\Laravel\FormDatalayer::class;
    }
}
