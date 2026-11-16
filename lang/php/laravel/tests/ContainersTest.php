<?php

declare(strict_types=1);

namespace Otaqq\FormDatalayer\Laravel\Tests;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Otaqq\FormDatalayer\Settings;

/**
 * Container ids entered on the settings screen, with the config default until then.
 */
final class ContainersTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('form-datalayer.containers', [
            'front' => ['label' => 'Public site', 'default' => 'GTM-DEFAULT1'],
            'admin' => ['label' => 'Admin', 'default' => ''],
        ]);
    }

    protected function defineRoutes($router): void
    {
        Route::middleware('web')->get('entry', fn () => view('page'))->name('front.entries.create');
    }

    protected function setUp(): void
    {
        parent::setUp();
        Gate::define('form-datalayer.manage', fn ($user) => $user->role === 'admin');
    }

    private function write(array $data): void
    {
        @mkdir(dirname($this->settingsPath), 0775, true);
        file_put_contents($this->settingsPath, json_encode($data));
    }

    public function test_the_config_default_is_used_until_an_id_is_saved(): void
    {
        $this->assertStringContainsString("'GTM-DEFAULT1'", $this->get('/entry')->getContent());

        $this->write(['version' => 1, 'containers' => ['front' => 'GTM-SAVED1']]);
        $this->assertStringContainsString("'GTM-SAVED1'", $this->get('/entry')->getContent());

        $this->write(['version' => 2, 'containers' => ['front' => '']]);
        $this->assertStringNotContainsString('googletagmanager', $this->get('/entry')->getContent());
    }

    public function test_the_screen_saves_every_declared_container_and_refuses_a_wrong_id(): void
    {
        $this->actingAs($this->user());
        $this->get('/form-datalayer')->assertOk()->assertSee('name="containers[front]" value="GTM-DEFAULT1"', false)->assertSee('Admin');

        $this->from('/form-datalayer')->put('/form-datalayer', ['version' => 0, 'containers' => ['front' => 'gtm-wrong', 'admin' => '']])
            ->assertSessionHasErrors('form-datalayer');
        $this->assertFileDoesNotExist($this->settingsPath);
        $this->get('/form-datalayer')->assertSee('value="gtm-wrong"', false);

        $this->put('/form-datalayer', ['version' => 0, 'containers' => ['front' => ' GTM-NEW1 ', 'admin' => '', 'other' => 'GTM-X1']]);
        $settings = Settings::fromFile($this->settingsPath);
        $this->assertSame(['GTM-NEW1', '', null], [$settings->container('front'), $settings->container('admin'), $settings->container('other')]);
    }

    public function test_a_saved_container_the_app_no_longer_declares_is_listed_as_removed(): void
    {
        $this->write(['version' => 1, 'containers' => ['gone' => 'GTM-OLD1']]);

        $this->actingAs($this->user())->get('/form-datalayer')->assertSee('unknown container: gone');
        $this->artisan('form-datalayer:check')->assertSuccessful()->expectsOutputToContain('unknown container: gone');
    }

    public function test_a_wrong_id_in_the_file_breaks_the_settings(): void
    {
        $this->write(['version' => 1, 'containers' => ['front' => 'UA-123']]);

        $this->assertTrue(Settings::fromFile($this->settingsPath)->isBroken());
    }
}
