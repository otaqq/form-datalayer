<?php

declare(strict_types=1);

namespace Otaqq\FormDatalayer\Laravel\Tests;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Otaqq\FormDatalayer\Laravel\Facades\FormDatalayer;

final class PushTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        Route::middleware('web')->group(function () {
            $posting = ['code' => '241116ab', 'prefecture_ids' => [13, 27, 13], 'secret' => 'not sent'];
            Route::get('entry', function () use ($posting) {
                FormDatalayer::with(['posting' => $posting]);

                return view('page');
            })->name('front.entries.create');
            Route::post('entry/confirm', function (Request $request) use ($posting) {
                $request->validate(['name' => 'required']);
                FormDatalayer::with(['posting' => $posting]);

                return view('page');
            })->name('front.entries.confirm');
            Route::get('jobs', fn () => view('page'))->name('front.jobs.index');
            Route::get('admin/job-postings/{id}/edit', fn () => view('page'))->name('admin.job-postings.edit');
            Route::put('admin/job-postings/{id}', function () {
                FormDatalayer::flash('admin.job-posting', 'complete', [], 'edit');

                return redirect('/admin/job-postings');
            });
            Route::get('admin/job-postings', fn () => view('page'));
        });
    }

    public function test_the_input_page_pushes_its_step_with_the_default_parameters_and_lookups(): void
    {
        $html = $this->get('/entry')->assertOk()->getContent();

        $this->assertSame([[
            'event' => 'front_entry_input', 'form' => 'front.entry', 'step' => 'input', 'posting_code' => '241116ab', 'prefectures' => 'Tokyo,Osaka',
        ]], self::pushesIn($html));
        $this->assertStringContainsString("'dataLayer','GTM-FRONT1'", $html);
        $this->assertStringContainsString('ns.html?id=GTM-FRONT1', $html);
        $this->assertStringNotContainsString('not sent', $html);
    }

    public function test_the_confirm_page_reads_the_request_input_when_chosen(): void
    {
        file_put_contents($this->settings(), json_encode(['version' => 1, 'forms' => ['front.entry' => ['steps' => ['confirm' => ['params' => ['desired' => true, 'prefectures' => false]]]]]]));

        $html = $this->post('/entry/confirm', ['name' => 'Taro', 'desired_prefecture_id' => '27'])->assertOk()->getContent();

        $this->assertSame([[
            'event' => 'front_entry_confirm', 'form' => 'front.entry', 'step' => 'confirm', 'posting_code' => '241116ab', 'desired' => 'Osaka',
        ]], self::pushesIn($html));
        $this->assertStringNotContainsString('Taro', $html);
    }

    public function test_an_input_page_back_with_validation_errors_is_the_error_step_with_the_old_input(): void
    {
        file_put_contents($this->settings(), json_encode(['version' => 1, 'forms' => ['front.entry' => ['steps' => ['error' => ['params' => ['desired' => true]]]]]]));

        $this->from('/entry')->post('/entry/confirm', ['desired_prefecture_id' => '13'])->assertRedirect('/entry');
        $html = $this->get('/entry')->getContent();

        $this->assertSame([[
            'event' => 'front_entry_error', 'form' => 'front.entry', 'step' => 'error', 'posting_code' => '241116ab', 'prefectures' => 'Tokyo,Osaka', 'desired' => 'Tokyo',
        ]], self::pushesIn($html));
    }

    public function test_a_flashed_push_appears_on_the_next_page_once_with_the_user_and_the_mode(): void
    {
        $this->actingAs($this->user('agency_staff'));

        $this->put('/admin/job-postings/1')->assertRedirect('/admin/job-postings');
        $this->assertSame([[
            'event' => 'admin_job_posting_complete', 'form' => 'admin.job-posting', 'step' => 'complete', 'mode' => 'edit', 'role' => 'agency_staff',
        ]], self::pushesIn($this->get('/admin/job-postings')->getContent()));
        $this->assertSame([], self::pushesIn($this->get('/admin/job-postings')->getContent()));
    }

    public function test_the_mode_of_the_page_is_pushed(): void
    {
        $this->actingAs($this->user());

        $this->assertSame('edit', self::pushesIn($this->get('/admin/job-postings/1/edit')->getContent())[0]['mode']);
    }

    public function test_forms_turned_off_by_the_catalog_or_the_settings_and_pages_not_in_the_catalog_push_nothing(): void
    {
        $this->assertSame([], self::pushesIn($this->get('/jobs')->getContent()));
        $this->assertSame([], self::pushesIn($this->get('/admin/job-postings')->getContent()));

        file_put_contents($this->settings(), json_encode(['version' => 1, 'forms' => ['front.entry' => ['enabled' => false]]]));
        $html = $this->get('/entry')->getContent();
        $this->assertSame([], self::pushesIn($html));
        $this->assertStringContainsString('GTM-FRONT1', $html, 'the tag stays');
    }

    public function test_without_a_container_nothing_is_written(): void
    {
        config(['form-datalayer.containers.front' => '']);

        $html = $this->get('/entry')->getContent();

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('noscript', $html);
    }

    public function test_broken_settings_stop_every_push_but_the_page_renders(): void
    {
        file_put_contents($this->settings(), '{broken');

        $html = $this->get('/entry')->assertOk()->getContent();

        $this->assertSame([], self::pushesIn($html));
    }

    private function settings(): string
    {
        @mkdir(dirname($this->settingsPath), 0775, true);

        return $this->settingsPath;
    }
}
