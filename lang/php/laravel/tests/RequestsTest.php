<?php

declare(strict_types=1);

namespace Otaqq\FormDatalayer\Laravel\Tests;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Otaqq\FormDatalayer\Laravel\Facades\FormDatalayer;

/**
 * Several requests in one process, contexts, error bags and pages that must render whatever the input.
 */
final class RequestsTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        Route::middleware('web')->group(function () {
            Route::get('entry', fn () => view('page'))->name('front.entries.create');
            Route::get('pushed', function () {
                FormDatalayer::with(['posting' => ['code' => 'from-with', 'prefecture_ids' => [13, 27]]]);
                FormDatalayer::with(['posting' => ['prefecture_ids' => [27]]]);
                FormDatalayer::push('front.entry', 'complete', ['posting' => ['code' => 'from-push']]);

                return view('page');
            });
            Route::post('named', function (Request $request) {
                Validator::make($request->all(), ['name' => 'required'])->validateWithBag('entry');
            });
            Route::post('flash-and-render', function () {
                FormDatalayer::flash('admin.job-posting', 'complete', [], 'edit');

                return view('page');
            });
            Route::get('other', fn () => view('page'));
        });
    }

    public function test_the_facade_follows_each_request(): void
    {
        $this->assertCount(1, self::pushesIn($this->get('/pushed')->getContent()));
        $this->assertCount(1, self::pushesIn($this->get('/pushed')->getContent()));
    }

    public function test_push_values_win_over_with_lists_are_replaced_and_the_input_is_there(): void
    {
        file_put_contents($this->settings(), json_encode(['version' => 1, 'forms' => ['front.entry' => ['steps' => ['complete' => ['params' => ['desired' => true]]]]]]));

        $push = self::pushesIn($this->get('/pushed?desired_prefecture_id=13')->getContent())[0];

        $this->assertSame(['from-push', 'Osaka', 'Tokyo'], [$push['posting_code'], $push['prefectures'], $push['desired']]);
    }

    public function test_errors_in_a_named_bag_make_the_error_step(): void
    {
        $this->from('/entry')->post('/named')->assertRedirect('/entry');

        $this->assertSame('error', self::pushesIn($this->get('/entry')->getContent())[0]['step']);
    }

    public function test_a_push_flashed_on_a_rendered_page_waits_for_the_next_page(): void
    {
        $this->assertSame([], self::pushesIn($this->post('/flash-and-render')->getContent()));
        $this->assertSame(['complete'], array_column(self::pushesIn($this->get('/other')->getContent()), 'step'));
        $this->assertSame([], self::pushesIn($this->get('/other')->getContent()));
    }

    public function test_input_that_json_cannot_carry_does_not_break_the_page(): void
    {
        file_put_contents($this->settings(), json_encode(['version' => 1, 'forms' => ['front.entry' => ['steps' => ['input' => ['params' => ['desired' => true]]]]]]));

        $this->get('/entry?desired_prefecture_id=%FF')->assertOk()->assertSee('GTM-FRONT1');
    }

    public function test_a_failing_lookup_finds_nothing_and_the_page_renders(): void
    {
        \Illuminate\Support\Facades\Schema::drop('prefectures');

        $html = $this->get('/pushed')->assertOk()->getContent();

        $this->assertSame('from-push', self::pushesIn($html)[0]['posting_code']);
        $this->assertArrayNotHasKey('prefectures', self::pushesIn($html)[0]);
    }

    private function settings(): string
    {
        @mkdir(dirname($this->settingsPath), 0775, true);

        return $this->settingsPath;
    }
}
