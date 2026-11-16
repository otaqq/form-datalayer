<?php

declare(strict_types=1);

namespace Otaqq\FormDatalayer\Laravel;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as Config;
use Otaqq\FormDatalayer\Exception\InvalidCatalog;
use Otaqq\FormDatalayer\Settings;

/**
 * Reports the catalog's problems, a broken settings file, and removed and new names (spec/admin.md#check-command).
 * Fails only for a rejected catalog or a broken settings file.
 */
final class CheckCommand extends Command
{
    protected $signature = 'form-datalayer:check';

    protected $description = 'Check the form-datalayer catalog and settings';

    public function handle(Config $config, FormDatalayer $datalayer): int
    {
        try {
            $catalog = $datalayer->catalog();
        } catch (InvalidCatalog $e) {
            $this->error("Catalog rejected: {$e->getMessage()}");

            return self::FAILURE;
        }
        $settings = Settings::fromFile((string) $config->get('form-datalayer.settings'));
        if ($settings->isBroken()) {
            $this->error("Settings broken, nothing is pushed: {$settings->broken}");

            return self::FAILURE;
        }

        $removed = $settings->stale($catalog, array_keys($datalayer->containers()));
        $new = [];
        foreach ($catalog->forms() as $key => $form) {
            if ($settings->isNewForm($key)) {
                $new[] = "new form (off): {$key}";
            }
            if ($form->steps === []) {
                $new[] = "form without pages: {$key}";
            }
            foreach ($form->stepNames() as $step) {
                foreach (array_keys($form->params) as $name) {
                    if ($settings->isNewParam($key, $step, $name)) {
                        $new[] = "new param: {$key}.{$step}.{$name}";
                    }
                }
            }
        }

        $this->info(sprintf('Catalog: %d forms. Settings: version %d.', count($catalog->forms()), $settings->version));
        foreach ([...$removed, ...$new] as $line) {
            $this->line($line);
        }
        if ($removed === [] && $new === []) {
            $this->line('No removed or new names.');
        }

        return self::SUCCESS;
    }
}
