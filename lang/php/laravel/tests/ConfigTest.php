<?php

declare(strict_types=1);

namespace Otaqq\FormDatalayer\Laravel\Tests;

/**
 * The default config of the package.
 */
final class ConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('FORM_DATALAYER_SETTINGS');
        unset($_ENV['FORM_DATALAYER_SETTINGS'], $_SERVER['FORM_DATALAYER_SETTINGS']);
        parent::tearDown();
    }

    private function settingsPath(?string $env): string
    {
        foreach ([fn ($v) => putenv("FORM_DATALAYER_SETTINGS={$v}"), fn ($v) => $_ENV['FORM_DATALAYER_SETTINGS'] = $v, fn ($v) => $_SERVER['FORM_DATALAYER_SETTINGS'] = $v] as $set) {
            $env === null ? null : $set($env);
        }

        return (require __DIR__.'/../config/form-datalayer.php')['settings'];
    }

    public function test_an_empty_or_missing_settings_path_uses_the_default(): void
    {
        $default = storage_path('app/private/form-datalayer.json');

        $this->assertSame($default, $this->settingsPath(null));
        $this->assertSame($default, $this->settingsPath(''));
        $this->assertSame('/srv/settings.json', $this->settingsPath('/srv/settings.json'));
    }
}
