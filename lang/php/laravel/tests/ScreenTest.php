<?php

declare(strict_types=1);

namespace Otaqq\FormDatalayer\Laravel\Tests;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Otaqq\FormDatalayer\Laravel\FormDatalayer;
use Otaqq\FormDatalayer\Settings;

final class ScreenTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('form-datalayer.groups', ['front' => 'Public site']);
    }

    protected function defineRoutes($router): void
    {
        Route::middleware('web')->group(function () {
            Route::get('entry', fn () => view('page'))->name('front.entries.create')->dataLayer('front.entry', 'input');
            Route::post('entry', fn () => redirect('/'))->dataLayer('front.entry', 'complete');
            Route::get('admin/job-postings/create', fn () => view('page'))->dataLayer('admin.job-posting', 'input', mode: 'create');
            Route::get('admin/export', fn () => view('page'))->dataLayer('admin.export', 'input');
        });
    }

    protected function setUp(): void
    {
        parent::setUp();
        Gate::define('form-datalayer.manage', fn ($user) => $user->role === 'admin');
    }

    public function test_only_users_with_the_ability_open_the_screen(): void
    {
        $this->actingAs($this->user('staff'))->get('/form-datalayer')->assertForbidden();
        $this->actingAs($this->user('staff'))->get('/form-datalayer/forms/front.entry')->assertForbidden();
        $this->actingAs($this->user())->get('/form-datalayer')->assertOk();
    }

    public function test_the_list_has_one_tab_per_group_and_new_forms_are_off_and_marked(): void
    {
        $page = $this->actingAs($this->user())->get('/form-datalayer')->assertOk();

        $page->assertSee('data-group="front"', false)->assertSee('>Public site</a>', false)->assertSee('data-group="admin"', false)
            ->assertSee('Job application')->assertSee('Inquiry')->assertDontSee('Job posting')
            ->assertSee('Off by the app');
        $this->assertSame(1, substr_count($page->getContent(), 'class="mark new"'), 'front.entry is new (the inquiry is off by the app)');
        $page->assertDontSee('name="enabled[front.entry]" value="1" checked', false);

        $admin = $this->get('/form-datalayer?group=admin')->assertOk();
        $admin->assertSee('Job posting')->assertSee('admin.export')->assertDontSee('Job application');
    }

    public function test_saving_the_list_writes_containers_and_the_forms_of_the_tab_only(): void
    {
        $this->saveSettings(['admin.job-posting' => ['enabled' => true, 'steps' => ['input' => ['event' => 'kept']]]]);
        $this->actingAs($this->user())->from('/form-datalayer')->put('/form-datalayer', [
            'version' => 1, 'group' => 'front',
            'containers' => ['front' => 'GTM-NEW1'],
            'enabled' => ['front.entry' => '1', 'admin.job-posting' => '0'],
        ])->assertRedirect('/form-datalayer')->assertSessionHas('form-datalayer.saved');

        $settings = Settings::fromFile($this->settingsPath);
        $this->assertSame([2, 'admin@example.com', true, true, 'kept', 'GTM-NEW1'], [
            $settings->version, $settings->updatedBy,
            $settings->formEnabled('front.entry'), $settings->formEnabled('admin.job-posting'),
            $settings->event('admin.job-posting', 'input'), $settings->container('front'),
        ]);
    }

    public function test_a_form_page_saves_that_form_alone(): void
    {
        $this->saveSettings(['admin.job-posting' => ['enabled' => true]], ['front' => 'GTM-KEEP1']);
        $this->actingAs($this->user());
        $this->get('/form-datalayer/forms/front.entry')->assertOk()
            ->assertSee('placeholder="front_entry_complete"', false)->assertSee('placeholder="front_entry_error"', false)
            ->assertDontSee('placeholder="front_entry_confirm"', false);

        $this->from('/form-datalayer/forms/front.entry')->put('/form-datalayer/forms/front.entry', [
            'version' => 1, 'enabled' => '1',
            'steps' => ['complete' => ['event' => ' entry_done ', 'params' => ['posting_code' => '1', 'desired' => '0']]],
        ])->assertRedirect('/form-datalayer/forms/front.entry');

        $settings = Settings::fromFile($this->settingsPath);
        $this->assertSame([true, 'entry_done', true, false, false, true, 'GTM-KEEP1'], [
            $settings->formEnabled('front.entry'), $settings->event('front.entry', 'complete'),
            $settings->param('front.entry', 'complete', 'posting_code'), $settings->param('front.entry', 'complete', 'desired'),
            $settings->param('front.entry', 'input', 'prefectures'), $settings->formEnabled('admin.job-posting'), $settings->container('front'),
        ]);
        $this->get('/form-datalayer/forms/front.gone')->assertNotFound();
    }

    public function test_a_form_page_save_keeps_steps_it_does_not_show_and_v02_view_steps_are_stale(): void
    {
        $this->saveSettings(['admin.job-posting' => ['enabled' => true, 'steps' => ['confirm' => ['event' => 'pushed_by_the_app'], 'view' => ['event' => 'old']]]]);
        $this->actingAs($this->user());
        $this->assertFalse(Settings::fromFile($this->settingsPath)->isBroken());
        $this->get('/form-datalayer')->assertSee('unknown step: admin.job-posting.view');

        $this->put('/form-datalayer/forms/admin.job-posting', ['version' => 1, 'enabled' => '1']);

        $settings = Settings::fromFile($this->settingsPath);
        $this->assertSame(['pushed_by_the_app', null], [$settings->event('admin.job-posting', 'confirm'), $settings->event('admin.job-posting', 'view')]);
    }

    public function test_a_save_from_an_older_version_is_refused_and_keeps_what_was_typed(): void
    {
        $this->actingAs($this->user());
        $this->put('/form-datalayer/forms/front.entry', ['version' => 0, 'enabled' => '1']);

        $this->from('/form-datalayer/forms/front.entry')->put('/form-datalayer/forms/front.entry', ['version' => 0, 'enabled' => '1', 'steps' => ['input' => ['event' => 'typed_name']]])
            ->assertRedirect('/form-datalayer/forms/front.entry')->assertSessionHas('form-datalayer.conflict');
        $this->get('/form-datalayer/forms/front.entry')->assertSee('data-test="conflict"', false)->assertSee('value="typed_name"', false)->assertSee('name="version" value="1"', false);
    }

    public function test_an_invalid_event_name_is_refused(): void
    {
        $this->actingAs($this->user())->from('/form-datalayer/forms/front.entry')
            ->put('/form-datalayer/forms/front.entry', ['version' => 0, 'enabled' => '1', 'steps' => ['input' => ['event' => 'has space']]])
            ->assertSessionHasErrors('form-datalayer');
        $this->assertFileDoesNotExist($this->settingsPath);
    }

    public function test_new_and_removed_names_are_marked_and_dropped_on_save(): void
    {
        $this->saveSettings([
            'front.gone' => ['enabled' => true],
            'front.entry' => ['enabled' => true, 'steps' => ['input' => ['params' => ['posting_code' => true, 'prefectures' => false, 'old_param' => true]]]],
        ], version: 3);
        $this->actingAs($this->user());

        $this->get('/form-datalayer')->assertSee('unknown form: front.gone')->assertSee('unknown param: front.entry.input.old_param')->assertSee('new parameters');
        $form = $this->get('/form-datalayer/forms/front.entry');
        // desired was added after the save, prefectures was saved unchecked
        $this->assertSame(1, substr_count($form->getContent(), 'class="mark new"'));

        $this->put('/form-datalayer', ['version' => 3, 'group' => 'front', 'enabled' => ['front.entry' => '1']]);
        $this->assertSame([], Settings::fromFile($this->settingsPath)->stale(app(FormDatalayer::class)->catalog()));
    }

    public function test_the_check_command_reports_removed_new_and_pageless_forms(): void
    {
        $this->saveSettings(['front.entry' => ['enabled' => true, 'steps' => ['input' => ['params' => ['posting_code' => true, 'gone' => true]]]]], version: 3);

        $this->artisan('form-datalayer:check')->assertSuccessful()
            ->expectsOutputToContain('unknown param: front.entry.input.gone')
            ->expectsOutputToContain('new param: front.entry.input.prefectures')
            ->expectsOutputToContain('new form (off): admin.job-posting');

        @unlink($this->settingsPath);
        file_put_contents($this->settingsPath, '{broken');
        $this->artisan('form-datalayer:check')->assertFailed();
    }
}
