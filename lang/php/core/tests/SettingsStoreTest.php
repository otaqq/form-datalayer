<?php

declare(strict_types=1);

namespace Otaqq\FormDatalayer\Tests;

use DateTimeImmutable;
use Otaqq\FormDatalayer\Catalog;
use Otaqq\FormDatalayer\Exception\InvalidSettings;
use Otaqq\FormDatalayer\Exception\SettingsConflict;
use Otaqq\FormDatalayer\Settings;
use Otaqq\FormDatalayer\SettingsStore;
use PHPUnit\Framework\TestCase;

final class SettingsStoreTest extends TestCase
{
    private string $dir;

    private string $path;

    private Catalog $catalog;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/form-datalayer-'.bin2hex(random_bytes(4));
        $this->path = "{$this->dir}/settings/form-datalayer.json";
        $this->catalog = Catalog::fromArray(['version' => 1, 'forms' => [
            'front.entry' => ['label' => 'Entry', 'steps' => ['input' => [], 'complete' => []], 'params' => ['code' => ['label' => 'Code', 'from' => 'code']]],
        ]]);
    }

    protected function tearDown(): void
    {
        foreach (is_dir("{$this->dir}/settings") ? scandir("{$this->dir}/settings") : [] as $file) {
            if (is_file("{$this->dir}/settings/{$file}")) {
                unlink("{$this->dir}/settings/{$file}");
            }
        }
        @rmdir("{$this->dir}/settings");
        @rmdir($this->dir);
    }

    public function test_a_missing_file_is_version_0_and_a_save_writes_version_1(): void
    {
        $logger = new TestLogger;
        $store = new SettingsStore($this->path, $this->catalog, $logger);
        $this->assertSame(0, $store->read()->version);

        $saved = $store->save(['front.entry' => ['steps' => ['complete' => ['event' => 'entry_done', 'params' => ['code' => true]]]]], 0, 'admin@example.com', new DateTimeImmutable('2026-11-16T10:00:00+09:00'));

        $this->assertSame(1, $saved->version);
        $read = $store->read();
        $this->assertSame([1, 'entry_done', true, '2026-11-16T10:00:00+09:00', 'admin@example.com'], [
            $read->version, $read->event('front.entry', 'complete'), $read->param('front.entry', 'complete', 'code'), $read->updatedAt, $read->updatedBy,
        ]);
        $this->assertSame(['settings saved'], $logger->of('info'));
        $this->assertSame('admin@example.com', $logger->records[0]['context']['by']);
        $this->assertSame([], glob("{$this->dir}/settings/.form-datalayer-*") ?: [], 'no temporary file is left');
    }

    public function test_empty_forms_are_saved_as_an_object(): void
    {
        $store = new SettingsStore($this->path, $this->catalog);

        $store->save([], 0, 'a');

        $this->assertStringContainsString('"forms": {}', (string) file_get_contents($this->path));
        $this->assertFalse($store->read()->isBroken());
    }

    public function test_a_save_from_an_older_version_is_refused_and_returns_the_current_settings(): void
    {
        $store = new SettingsStore($this->path, $this->catalog);
        $store->save(['front.entry' => ['enabled' => false]], 0, 'first');

        try {
            $store->save(['front.entry' => ['enabled' => true]], 0, 'second');
            $this->fail('No conflict');
        } catch (SettingsConflict $e) {
            $this->assertSame(1, $e->current->version);
            $this->assertFalse($e->current->formEnabled('front.entry'));
        }
        $this->assertFalse($store->read()->formEnabled('front.entry'));
    }

    public function test_names_the_catalog_does_not_have_and_schema_errors_are_refused(): void
    {
        $store = new SettingsStore($this->path, $this->catalog);

        try {
            $store->save([
                'front.gone' => ['enabled' => false],
                'front.entry' => ['steps' => ['confirm' => ['event' => 'x'], 'complete' => ['event' => 'has space', 'params' => ['gone' => true]]]],
            ], 0, 'a');
            $this->fail('Not refused');
        } catch (InvalidSettings $e) {
            $this->assertSame(['forms.front.entry.steps.complete.event is not a valid event name'], $e->errors);
        }

        try {
            $store->save(['front.gone' => ['enabled' => false], 'front.entry' => ['steps' => ['confirm' => ['event' => 'x'], 'complete' => ['params' => ['gone' => true]]]]], 0, 'a');
            $this->fail('Not refused');
        } catch (InvalidSettings $e) {
            $this->assertSame(['unknown form: front.gone', 'unknown param: front.entry.complete.gone'], $e->errors);
        }
        $this->assertFileDoesNotExist($this->path);
    }

    public function test_a_broken_file_can_be_replaced_from_version_0(): void
    {
        mkdir(dirname($this->path), 0775, true);
        file_put_contents($this->path, '{not json');
        $store = new SettingsStore($this->path, $this->catalog);
        $this->assertTrue($store->read()->isBroken());
        $this->assertSame(0, $store->read()->version);

        $store->save([], 0, 'a');

        $this->assertFalse($store->read()->isBroken());
    }

    public function test_settings_read_back_as_saved(): void
    {
        $data = ['version' => 2, 'updated_by' => 'a', 'forms' => ['front.entry' => ['enabled' => false]]];

        $this->assertSame($data, Settings::fromArray($data)->toArray());
    }
}
