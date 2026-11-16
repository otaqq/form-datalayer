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
            })->name('front.entries.create')->dataLayer('front.entry', 'input');
            Route::post('entry/confirm', function (Request $request) use ($posting) {
                $request->validate(['name' => 'required']);
                FormDatalayer::with(['posting' => $posting]);

                return view('page');
            })->name('front.entries.confirm')->dataLayer('front.entry', 'confirm');
            Route::get('inquiry', fn () => view('page'))->name('front.inquiries.create');
            Route::get('terms', fn () => view('page'));
            Route::get('admin/job-postings/{id}/edit', fn () => view('page'))->dataLayer('admin.job-posting', 'input', mode: 'edit');
            Route::put('admin/job-postings/{id}', function (Request $request) {
                if ($request->boolean('fail')) {
                    return redirect('/admin/job-postings')->with('error', 'Expired');
                }
                FormDatalayer::mode('edit');

                return redirect('/admin/job-postings');
            })->dataLayer('admin.job-posting', 'complete');
            Route::get('admin/job-postings', fn () => view('page'));
            // Unnamed routes in a named group get the group prefix as name
            Route::name('admin.')->group(function () {
                Route::post('admin/a/confirm', fn () => redirect('/'))->dataLayer('admin.a', 'complete');
                Route::post('admin/b/confirm', fn () => redirect('/'))->dataLayer('admin.b', 'complete');
            });
            // A route middleware that redirects before the action (like a password confirmation)
            Route::put('admin/guarded', fn () => redirect('/admin/job-postings'))
                ->middleware(Support\RedirectFirst::class)
                ->dataLayer('admin.job-posting', 'complete');
            Route::post('admin/job-postings/flash', function () {
                FormDatalayer::flash('admin.job-posting', 'complete', [], 'create');

                return redirect('/admin/job-postings');
            });
        });
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('form-datalayer.failures', ['error']);
    }

    public function test_forms_are_off_until_turned_on(): void
    {
        $html = $this->get('/entry')->assertOk()->getContent();

        $this->assertSame([], self::pushesIn($html));
        $this->assertStringContainsString("'dataLayer','GTM-FRONT1'", $html, 'the tag stays');
    }

    public function test_the_input_page_pushes_its_step_with_the_default_parameters_and_lookups(): void
    {
        $this->saveSettings(['front.entry' => ['enabled' => true]]);

        $html = $this->get('/entry')->assertOk()->getContent();

        $this->assertSame([[
            'event' => 'front_entry_input', 'form' => 'front.entry', 'step' => 'input', 'posting_code' => '241116ab', 'prefectures' => 'Tokyo,Osaka',
        ]], self::pushesIn($html));
        $this->assertStringContainsString('ns.html?id=GTM-FRONT1', $html);
        $this->assertStringNotContainsString('not sent', $html);
    }

    public function test_the_confirm_page_reads_the_request_input_when_chosen(): void
    {
        $this->saveSettings(['front.entry' => ['enabled' => true, 'steps' => ['confirm' => ['params' => ['desired' => true, 'prefectures' => false]]]]]);

        $html = $this->post('/entry/confirm', ['name' => 'Taro', 'desired_prefecture_id' => '27'])->assertOk()->getContent();

        $this->assertSame([[
            'event' => 'front_entry_confirm', 'form' => 'front.entry', 'step' => 'confirm', 'posting_code' => '241116ab', 'desired' => 'Osaka',
        ]], self::pushesIn($html));
        $this->assertStringNotContainsString('Taro', $html);
    }

    public function test_an_input_page_back_with_validation_errors_is_the_error_step_with_the_old_input(): void
    {
        $this->saveSettings(['front.entry' => ['enabled' => true, 'steps' => ['error' => ['params' => ['desired' => true]]]]]);

        $this->from('/entry')->post('/entry/confirm', ['desired_prefecture_id' => '13'])->assertRedirect('/entry');
        $html = $this->get('/entry')->getContent();

        $this->assertSame([[
            'event' => 'front_entry_error', 'form' => 'front.entry', 'step' => 'error', 'posting_code' => '241116ab', 'prefectures' => 'Tokyo,Osaka', 'desired' => 'Tokyo',
        ]], self::pushesIn($html));
    }

    public function test_a_complete_route_that_redirects_pushes_complete_on_the_next_page_with_the_mode(): void
    {
        $this->saveSettings(['admin.job-posting' => ['enabled' => true]]);
        $this->actingAs($this->user('agency_staff'));

        $this->put('/admin/job-postings/1')->assertRedirect('/admin/job-postings');
        $this->assertSame([[
            'event' => 'admin_job_posting_complete', 'form' => 'admin.job-posting', 'step' => 'complete', 'mode' => 'edit', 'role' => 'agency_staff',
        ]], self::pushesIn($this->get('/admin/job-postings')->getContent()));
        $this->assertSame([], self::pushesIn($this->get('/admin/job-postings')->getContent()));
    }

    public function test_a_complete_route_that_flashes_a_failure_pushes_nothing(): void
    {
        $this->saveSettings(['admin.job-posting' => ['enabled' => true]]);
        $this->actingAs($this->user());

        $this->put('/admin/job-postings/1', ['fail' => 1])->assertRedirect('/admin/job-postings');

        $this->assertSame([], self::pushesIn($this->get('/admin/job-postings')->getContent()));
    }

    public function test_a_redirect_from_a_route_middleware_is_not_a_complete(): void
    {
        $this->saveSettings(['admin.job-posting' => ['enabled' => true]]);
        $this->actingAs($this->user());

        $this->put('/admin/guarded')->assertRedirect('/admin/job-postings');

        $this->assertSame([], self::pushesIn($this->get('/admin/job-postings')->getContent()));
    }

    public function test_a_catalog_error_never_breaks_a_complete_submit(): void
    {
        $this->saveSettings(['admin.job-posting' => ['enabled' => true]]);
        $this->actingAs($this->user());
        Route::middleware('web')->get('broken', fn () => 'x')->dataLayer('admin.job-posting', 'view');

        $this->put('/admin/job-postings/1')->assertRedirect('/admin/job-postings');
    }

    public function test_the_app_can_still_flash_a_step_itself(): void
    {
        $this->saveSettings(['admin.job-posting' => ['enabled' => true]]);
        $this->actingAs($this->user());

        $this->post('/admin/job-postings/flash');

        $this->assertSame('create', self::pushesIn($this->get('/admin/job-postings')->getContent())[0]['mode']);
    }

    public function test_the_mode_of_the_page_is_pushed(): void
    {
        $this->saveSettings(['admin.job-posting' => ['enabled' => true]]);
        $this->actingAs($this->user());

        $this->assertSame('edit', self::pushesIn($this->get('/admin/job-postings/1/edit')->getContent())[0]['mode']);
    }

    public function test_forms_turned_off_by_the_catalog_and_pages_without_a_form_push_nothing(): void
    {
        $this->saveSettings(['front.inquiry' => ['enabled' => true]]);

        $this->assertSame([], self::pushesIn($this->get('/inquiry')->getContent()));
        $this->assertSame([], self::pushesIn($this->get('/terms')->getContent()));
    }

    public function test_without_a_container_nothing_is_written(): void
    {
        config(['form-datalayer.containers.front' => '']);
        $this->saveSettings(['front.entry' => ['enabled' => true]]);

        $html = $this->get('/entry')->getContent();

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('noscript', $html);
    }

    public function test_broken_settings_stop_every_push_but_the_page_renders(): void
    {
        @mkdir(dirname($this->settingsPath), 0775, true);
        file_put_contents($this->settingsPath, '{broken');

        $this->assertSame([], self::pushesIn($this->get('/entry')->assertOk()->getContent()));
    }

    public function test_route_pages_are_in_the_catalog(): void
    {
        $catalog = app(\Otaqq\FormDatalayer\Laravel\FormDatalayer::class)->catalog();

        $this->assertSame(['input', 'confirm', 'error'], $catalog->form('front.entry')->stepNames());
        $this->assertSame(['input', 'complete', 'error'], $catalog->form('admin.job-posting')->stepNames());
        $this->assertSame('complete', $catalog->page('PUT admin/job-postings/{id}')?->step);
        $this->assertSame(['admin.a', 'admin.b'], [$catalog->page('POST admin/a/confirm')?->form, $catalog->page('POST admin/b/confirm')?->form]);
    }
}
