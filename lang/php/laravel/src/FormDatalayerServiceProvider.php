<?php

declare(strict_types=1);

namespace Otaqq\FormDatalayer\Laravel;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Otaqq\FormDatalayer\Lookup;
use Otaqq\FormDatalayer\SettingsStore;

/**
 * Config, the per-request FormDatalayer, Blade directives, the settings screen and the check command.
 */
final class FormDatalayerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/form-datalayer.php', 'form-datalayer');

        $this->app->bind(Lookup::class, fn ($app) => new DatabaseLookup($app['db'], $app['config']->get('form-datalayer.connection'), $app['log']));
        $this->app->scoped(FormDatalayer::class, fn ($app) => new FormDatalayer($app['config'], $app['request'], $app['auth'], $app->make(Lookup::class), $app['log']));
        // One FormDatalayer per request, also when a process handles several (tests, Octane)
        $this->app->rebinding('request', function ($app) {
            $app->forgetInstance(FormDatalayer::class);
            Facades\FormDatalayer::clearResolvedInstance();
        });
        $this->app->bind(SettingsStore::class, fn ($app) => new SettingsStore(
            (string) $app['config']->get('form-datalayer.settings'),
            $app->make(FormDatalayer::class)->catalog(),
            $app['log'],
            array_keys($app->make(FormDatalayer::class)->containers()),
        ));
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'form-datalayer');
        $this->publishes([__DIR__.'/../config/form-datalayer.php' => config_path('form-datalayer.php')], 'form-datalayer-config');
        $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/form-datalayer')], 'form-datalayer-views');

        Blade::directive('formDatalayerHead', fn (string $container) => '<?php echo app('.FormDatalayer::class.'::class)->head('.($container === '' ? "'default'" : $container).'); ?>');
        Blade::directive('formDatalayerBody', fn (string $container) => '<?php echo app('.FormDatalayer::class.'::class)->body('.($container === '' ? "'default'" : $container).'); ?>');

        /** @var Config $config */
        $config = $this->app['config'];
        if ($config->get('form-datalayer.screen.enabled', true)) {
            Route::prefix((string) $config->get('form-datalayer.screen.prefix'))
                ->name((string) $config->get('form-datalayer.screen.name'))
                ->middleware((array) $config->get('form-datalayer.screen.middleware'))
                ->group(__DIR__.'/../routes/screen.php');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([CheckCommand::class]);
        }
    }
}
