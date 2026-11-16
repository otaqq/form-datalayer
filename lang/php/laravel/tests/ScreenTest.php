<?php

declare(strict_types=1);

namespace Otaqq\FormDatalayer\Laravel\Tests;

use Illuminate\Support\Facades\Gate;
use Otaqq\FormDatalayer\Settings;

final class ScreenTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Gate::define('form-datalayer.manage', fn ($user) => $user->role === 'admin');
    }

    public function test_only_users_with_the_ability_open_the_screen(): void
    {
        $this->actingAs($this->user('staff'))->get('/form-datalayer')->assertForbidden();
        $this->actingAs($this->user())->get('/form-datalayer')->assertOk()
            ->assertSee('Job application')
            ->assertSee('placeholder="front_entry_confirm"', false)
            ->assertSee('Turned off by the app.');
    }

    public function test_saving_writes_every_form_step_and_parameter(): void
    {
        $this->actingAs($this->user())->from('/form-datalayer')->put('/form-datalayer', [
            'version' => 0,
            'forms' => [
                'front.entry' => ['enabled' => '1', 'steps' => ['complete' => ['event' => ' entry_done ', 'params' => ['posting_code' => '1', 'desired' => '0']]]],
                'admin.job-posting' => ['enabled' => '0'],
                'front.unknown' => ['enabled' => '1'],
            ],
        ])->assertRedirect('/form-datalayer')->assertSessionHas('form-datalayer.saved');

        $settings = Settings::fromFile($this->settingsPath);
        $this->assertSame(1, $settings->version);
        $this->assertSame('admin@example.com', $settings->updatedBy);
        $this->assertSame('entry_done', $settings->event('front.entry', 'complete'));
        $this->assertNull($settings->event('front.entry', 'input'));
        $this->assertSame([true, false, false], [
            $settings->param('front.entry', 'complete', 'posting_code'),
            $settings->param('front.entry', 'complete', 'desired'),
            $settings->param('front.entry', 'input', 'prefectures'),
        ]);
        $this->assertFalse($settings->formEnabled('admin.job-posting'));
        $this->assertSame([], $settings->stale(app(\Otaqq\FormDatalayer\Laravel\FormDatalayer::class)->catalog()));
    }

    public function test_a_save_from_an_older_version_is_refused_and_keeps_what_was_typed(): void
    {
        $this->actingAs($this->user());
        $this->put('/form-datalayer', ['version' => 0, 'forms' => []]);

        $this->from('/form-datalayer')->put('/form-datalayer', ['version' => 0, 'forms' => ['front.entry' => ['enabled' => '1', 'steps' => ['input' => ['event' => 'typed_name']]]]])
            ->assertRedirect('/form-datalayer')->assertSessionHas('form-datalayer.conflict');
        $this->get('/form-datalayer')->assertSee('data-test="conflict"', false)->assertSee('value="typed_name"', false)->assertSee('name="version" value="1"', false);
    }

    public function test_an_invalid_event_name_is_refused(): void
    {
        $this->actingAs($this->user())->from('/form-datalayer')
            ->put('/form-datalayer', ['version' => 0, 'forms' => ['front.entry' => ['enabled' => '1', 'steps' => ['input' => ['event' => 'has space']]]]])
            ->assertSessionHasErrors('form-datalayer');
        $this->assertFileDoesNotExist($this->settingsPath);
    }

    public function test_new_and_removed_names_are_marked(): void
    {
        @mkdir(dirname($this->settingsPath), 0775, true);
        file_put_contents($this->settingsPath, json_encode(['version' => 3, 'forms' => [
            'front.gone' => ['enabled' => false],
            'front.entry' => ['steps' => ['input' => ['params' => ['posting_code' => true, 'prefectures' => false, 'old_param' => true]]]],
        ]]));

        $page = $this->actingAs($this->user())->get('/form-datalayer')->assertOk();

        $page->assertSee('unknown form: front.gone')->assertSee('unknown param: front.entry.input.old_param');
        // desired was added after the save, prefectures was saved unchecked
        $this->assertSame(1, substr_count($page->getContent(), 'class="mark new"'));
        $page->assertDontSee('name="forms[front.entry][steps][input][params][prefectures]" value="1" checked', false);
    }

    public function test_the_check_command_reports_removed_and_new_names(): void
    {
        @mkdir(dirname($this->settingsPath), 0775, true);
        file_put_contents($this->settingsPath, json_encode(['version' => 3, 'forms' => ['front.entry' => ['steps' => ['input' => ['params' => ['posting_code' => true, 'gone' => true]]]]]]));

        $this->artisan('form-datalayer:check')->assertSuccessful()
            ->expectsOutputToContain('unknown param: front.entry.input.gone')
            ->expectsOutputToContain('new param: front.entry.input.prefectures');

        file_put_contents($this->settingsPath, '{broken');
        $this->artisan('form-datalayer:check')->assertFailed();

        config(['form-datalayer.catalog' => __DIR__.'/missing.json']);
        $this->artisan('form-datalayer:check')->assertFailed();
    }
}
