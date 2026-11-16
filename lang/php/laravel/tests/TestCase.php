<?php

declare(strict_types=1);

namespace Otaqq\FormDatalayer\Laravel\Tests;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Otaqq\FormDatalayer\Laravel\FormDatalayerServiceProvider;

abstract class TestCase extends Orchestra
{
    protected string $settingsPath;

    protected function getPackageProviders($app): array
    {
        return [FormDatalayerServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $this->settingsPath = sys_get_temp_dir().'/form-datalayer-laravel-'.bin2hex(random_bytes(4)).'/form-datalayer.json';
        $app['config']->set([
            'app.key' => 'base64:'.base64_encode(str_repeat('k', 32)),
            'database.default' => 'testing',
            'form-datalayer.catalog' => __DIR__.'/fixtures/catalog.json',
            'form-datalayer.settings' => $this->settingsPath,
            'form-datalayer.containers.front' => 'GTM-FRONT1',
            'form-datalayer.user' => ['role'],
            'view.paths' => [__DIR__.'/views'],
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('prefectures', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
        DB::table('prefectures')->insert([['id' => 13, 'name' => 'Tokyo'], ['id' => 27, 'name' => 'Osaka']]);
    }

    protected function tearDown(): void
    {
        $dir = dirname($this->settingsPath);
        foreach (is_dir($dir) ? scandir($dir) : [] as $file) {
            if (is_file("{$dir}/{$file}")) {
                unlink("{$dir}/{$file}");
            }
        }
        @rmdir(dirname($this->settingsPath));
        parent::tearDown();
    }

    protected function user(string $role = 'admin'): GenericUser
    {
        return new GenericUser(['id' => 1, 'email' => 'admin@example.com', 'role' => $role, 'remember_token' => null]);
    }

    /**
     * The pushes written into a page.
     *
     * @return list<array<string, mixed>>
     */
    protected static function pushesIn(string $html): array
    {
        preg_match_all('/window\.dataLayer\.push\((\{.*?\})\);/', $html, $m);

        return array_map(fn (string $json) => json_decode($json, true, 512, JSON_THROW_ON_ERROR), $m[1]);
    }
}
