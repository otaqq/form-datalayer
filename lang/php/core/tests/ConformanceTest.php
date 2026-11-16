<?php

declare(strict_types=1);

namespace Otaqq\FormDatalayer\Tests;

use Otaqq\FormDatalayer\ArrayLookup;
use Otaqq\FormDatalayer\Catalog;
use Otaqq\FormDatalayer\Exception\InvalidCatalog;
use Otaqq\FormDatalayer\Names;
use Otaqq\FormDatalayer\Output;
use Otaqq\FormDatalayer\Param;
use Otaqq\FormDatalayer\Pusher;
use Otaqq\FormDatalayer\Settings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The shared data of conformance/ (see conformance/README.md).
 */
final class ConformanceTest extends TestCase
{
    /**
     * @return array<mixed>
     */
    private static function data(string $file): array
    {
        return json_decode((string) file_get_contents(__DIR__."/../../../../conformance/{$file}"), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @return iterable<string, array{array<mixed>}>
     */
    public static function catalogCases(): iterable
    {
        foreach (self::data('catalog.json')['cases'] as $case) {
            yield $case['name'] => [$case];
        }
    }

    /**
     * @param  array<mixed>  $case
     */
    #[DataProvider('catalogCases')]
    public function test_catalog(array $case): void
    {
        if (! $case['valid']) {
            $this->expectException(InvalidCatalog::class);
        }
        $catalog = Catalog::fromArray($case['catalog']);

        foreach ($case['event_names'] ?? [] as $form => $names) {
            $actual = [];
            foreach ($catalog->form($form)->stepNames() as $step) {
                $actual[$step] = Names::defaultEventName($form, $step);
            }
            ksort($actual);
            ksort($names);
            $this->assertSame($names, $actual, $form);
        }
        foreach ($case['pages'] ?? [] as $id => $expected) {
            $page = $catalog->page($id);
            $this->assertNotNull($page, $id);
            $this->assertSame([$expected['form'], $expected['step'], $expected['mode'] ?? null], [$page->form, $page->step, $page->mode], $id);
        }
        $this->assertNull($catalog->page('not-a-page'));
    }

    /**
     * @return iterable<string, array{array<mixed>, array<mixed>, array<mixed>}>
     */
    public static function valueCases(): iterable
    {
        $data = self::data('values.json');
        foreach ($data['cases'] as $i => $case) {
            yield "{$i}: {$case['from']}" => [$case, $data['context'], $data['lookups']];
        }
    }

    /**
     * @param  array<mixed>  $case
     * @param  array<mixed>  $context
     * @param  array<mixed>  $lookups
     */
    #[DataProvider('valueCases')]
    public function test_values(array $case, array $context, array $lookups): void
    {
        $param = ['label' => 'P', 'from' => $case['from']];
        foreach (['join', 'max_length', 'lookup'] as $field) {
            if (array_key_exists($field, $case)) {
                $param[$field] = $case[$field];
            }
        }
        $catalog = Catalog::fromArray(['version' => 1, 'forms' => ['f' => ['label' => 'F', 'steps' => ['view' => []], 'params' => ['p' => [...$param, 'default' => true]]]]]);
        $logger = new TestLogger;

        $push = (new Pusher($catalog, Settings::empty(), new ArrayLookup($lookups), $logger))->push('f', 'view', $context);

        $this->assertSame($case['value'], $push['p'] ?? null);
        $this->assertSame($case['warning'] ?? false, $logger->of('warning') !== []);
    }

    /**
     * @return iterable<string, array{array<mixed>, array<mixed>}>
     */
    public static function pushCases(): iterable
    {
        $data = self::data('push.json');
        foreach ($data['cases'] as $case) {
            yield $case['name'] => [$case, $data];
        }
    }

    /**
     * @param  array<mixed>  $case
     * @param  array<mixed>  $data
     */
    #[DataProvider('pushCases')]
    public function test_push(array $case, array $data): void
    {
        $settings = match (true) {
            $case['settings'] === null => Settings::empty(),
            is_string($case['settings']) => Settings::fromJson($case['settings']),
            default => Settings::fromArray($case['settings']),
        };
        $logger = new TestLogger;
        $pusher = new Pusher(Catalog::fromArray($data['catalog']), $settings, new ArrayLookup($data['lookups']), $logger);

        $push = $pusher->push($case['form'], $case['step'], $data['context'], $case['mode'] ?? null);

        $this->assertSame($case['push'], $push);
        $this->assertSame($case['warnings'] ?? [], $logger->of('warning'));
        $this->assertSame(isset($case['error']), $logger->of('error') !== []);
    }

    /**
     * @return iterable<string, array{array<mixed>}>
     */
    public static function outputCases(): iterable
    {
        foreach (self::data('output.json')['cases'] as $case) {
            yield $case['name'] => [$case];
        }
    }

    /**
     * @param  array<mixed>  $case
     */
    #[DataProvider('outputCases')]
    public function test_output(array $case): void
    {
        $this->assertSame($case['script'], Output::pushScript($case['pushes'], $case['variable']));
    }

    public function test_container_ids_and_variables(): void
    {
        $data = self::data('output.json');
        foreach ($data['container_ids']['valid'] as $id) {
            $this->assertTrue(Output::isContainerId($id), $id);
        }
        foreach ($data['container_ids']['invalid'] as $id) {
            $this->assertFalse(Output::isContainerId($id), $id);
        }
        foreach ($data['variables']['valid'] as $variable) {
            $this->assertTrue(Output::isVariable($variable), $variable);
        }
        foreach ($data['variables']['invalid'] as $variable) {
            $this->assertFalse(Output::isVariable($variable), $variable);
        }
    }

    public function test_the_example_catalog_of_the_spec_loads(): void
    {
        preg_match('/```json\n(.*?)\n```/s', (string) file_get_contents(__DIR__.'/../../../../spec/catalog.md'), $m);

        $catalog = Catalog::fromJson($m[1]);

        $this->assertInstanceOf(Param::class, $catalog->form('front.entry')->params['prefectures']);
    }
}
