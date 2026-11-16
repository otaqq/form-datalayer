<?php

declare(strict_types=1);

namespace Otaqq\FormDatalayer\Laravel\Tests;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Otaqq\FormDatalayer\Settings;

/**
 * Container ids entered on the settings screen, with the config default until then, and a follower container.
 */
final class ContainersTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('form-datalayer.containers', [
            'front' => ['label' => 'Public site', 'default' => 'GTM-DEFAULT1'],
            'admin' => ['label' => 'Admin', 'follows' => 'front'],
        ]);
    }

    protected function defineRoutes($router): void
    {
        Route::middleware('web')->get('entry', fn () => view('page'))->name('front.entries.create')->dataLayer('front.entry', 'input');
        Route::middleware('web')->get('admin', fn () => view('admin'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        Gate::define('form-datalayer.manage', fn ($user) => $user->role === 'admin');
        file_put_contents(__DIR__.'/views/admin.blade.php', "<html><head>@formDatalayerHead('admin')</head><body></body></html>\n");
    }

    protected function tearDown(): void
    {
        @unlink(__DIR__.'/views/admin.blade.php');
        parent::tearDown();
    }

    public function test_the_config_default_is_used_until_an_id_is_saved(): void
    {
        $this->assertStringContainsString("'GTM-DEFAULT1'", $this->get('/entry')->getContent());

        $this->saveSettings([], ['front' => 'GTM-SAVED1']);
        $this->assertStringContainsString("'GTM-SAVED1'", $this->get('/entry')->getContent());

        $this->saveSettings([], ['front' => ''], 2);
        $this->assertStringNotContainsString('googletagmanager', $this->get('/entry')->getContent());
    }

    public function test_a_follower_is_a_checkbox_that_copies_the_followed_id(): void
    {
        $this->actingAs($this->user());
        $this->get('/form-datalayer')->assertOk()->assertSee('name="containers[front]" value="GTM-DEFAULT1"', false)
            ->assertSee('type="checkbox" name="containers[admin]"', false)->assertDontSee('name="containers[admin]" value="GTM', false);

        $this->put('/form-datalayer', ['version' => 0, 'group' => 'front', 'containers' => ['front' => ' GTM-NEW1 ', 'admin' => '1', 'other' => 'GTM-X1']]);
        $settings = Settings::fromFile($this->settingsPath);
        $this->assertSame(['GTM-NEW1', 'GTM-NEW1', null], [$settings->container('front'), $settings->container('admin'), $settings->container('other')]);
        $this->assertStringContainsString("'GTM-NEW1'", $this->get('/admin')->getContent());

        $this->put('/form-datalayer', ['version' => 1, 'group' => 'front', 'containers' => ['front' => 'GTM-NEW1', 'admin' => '0']]);
        $this->assertSame('', Settings::fromFile($this->settingsPath)->container('admin'));
        $this->assertStringNotContainsString('googletagmanager', $this->get('/admin')->getContent());
    }

    public function test_a_wrong_id_is_refused_on_save_and_breaks_a_file(): void
    {
        $this->actingAs($this->user())->from('/form-datalayer')->put('/form-datalayer', ['version' => 0, 'group' => 'front', 'containers' => ['front' => 'gtm-wrong']])
            ->assertSessionHasErrors('form-datalayer');
        $this->assertFileDoesNotExist($this->settingsPath);
        $this->get('/form-datalayer')->assertSee('value="gtm-wrong"', false);

        $this->saveSettings([], ['front' => 'UA-123']);
        $this->assertTrue(Settings::fromFile($this->settingsPath)->isBroken());
    }

    public function test_a_saved_container_the_app_no_longer_declares_is_listed_as_removed(): void
    {
        $this->saveSettings([], ['gone' => 'GTM-OLD1']);

        $this->actingAs($this->user())->get('/form-datalayer')->assertSee('unknown container: gone');
        $this->artisan('form-datalayer:check')->assertSuccessful()->expectsOutputToContain('unknown container: gone');
    }
}
