<?php

declare(strict_types=1);

namespace Otaqq\FormDatalayer;

use DateTimeImmutable;
use DateTimeInterface;
use Otaqq\FormDatalayer\Exception\InvalidSettings;
use Otaqq\FormDatalayer\Exception\SettingsConflict;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

/**
 * Reads and saves the settings file (spec/settings.md#saving): an exclusive lock, an optimistic version check,
 * the schema and catalog checks, then a temporary file renamed over the file. Every save is logged.
 */
final class SettingsStore
{
    /**
     * @param  list<string>  $containers  The container names the app declares
     */
    public function __construct(
        private readonly string $path,
        private readonly Catalog $catalog,
        private readonly LoggerInterface $logger = new NullLogger,
        private readonly array $containers = [],
    ) {}

    public function read(): Settings
    {
        return Settings::fromFile($this->path);
    }

    /**
     * Replaces the settings.
     *
     * @param  array<string, mixed>  $forms  Form key → form settings, as in the file
     * @param  int  $version  The version the screen was opened with
     * @param  array<string, string>  $containers  Container name → GTM container id ('' for none)
     *
     * @throws SettingsConflict when someone else saved first
     * @throws InvalidSettings when the settings fail the schema or name something the catalog does not have
     */
    public function save(array $forms, int $version, string $by, ?DateTimeInterface $now = null, array $containers = []): Settings
    {
        $dir = dirname($this->path);
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException("Cannot create {$dir}");
        }
        $lock = @fopen($this->path.'.lock', 'c');
        if ($lock === false) {
            throw new RuntimeException("Cannot open {$this->path}.lock");
        }

        try {
            if (! flock($lock, LOCK_EX)) {
                throw new RuntimeException("Cannot lock {$this->path}.lock");
            }
            $current = $this->read();
            if ($current->version !== $version) {
                throw new SettingsConflict($current);
            }

            $data = [
                'version' => $version + 1,
                'updated_at' => ($now ?? new DateTimeImmutable)->format(DATE_ATOM),
                'updated_by' => $by,
                'containers' => $containers,
                'forms' => $forms,
            ];
            if ($containers === []) {
                unset($data['containers']);
            }
            $errors = [...Settings::validate($data), ...$this->catalogErrors($forms, $containers)];
            if ($errors !== []) {
                throw new InvalidSettings($errors);
            }

            $this->write($data);
            $this->logger->info('form-datalayer: settings saved', [
                'by' => $by,
                'version' => $version + 1,
                'before' => $current->toArray(),
                'after' => $data,
            ]);

            return Settings::fromArray($data);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * On save, names the catalog does not have are mistakes, not history.
     *
     * @param  array<mixed>  $forms
     * @return list<string>
     */
    private function catalogErrors(array $forms, array $containers): array
    {
        $settings = Settings::fromArray(['version' => 0, 'containers' => $containers, 'forms' => $forms]);

        return $settings->isBroken() ? [] : $settings->stale($this->catalog, $this->containers);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function write(array $data): void
    {
        // The settings hold no lists, so every array is written as an object ("forms": {} stays an object)
        $json = json_encode($data, JSON_FORCE_OBJECT | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
        $temp = @tempnam(dirname($this->path), '.form-datalayer-');
        if ($temp === false) {
            throw new RuntimeException("Cannot write the settings next to {$this->path}");
        }
        $handle = @fopen($temp, 'w');
        $written = $handle !== false && @fwrite($handle, $json) === strlen($json) && fflush($handle) && fsync($handle);
        if ($handle !== false) {
            fclose($handle);
        }
        // updated_by is a person: readable by the app's user and group only
        @chmod($temp, 0660);
        if (! $written || ! @rename($temp, $this->path)) {
            @unlink($temp);
            throw new RuntimeException("Cannot replace {$this->path}");
        }
    }
}
