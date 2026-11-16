<?php

declare(strict_types=1);

namespace Otaqq\FormDatalayer\Tests;

use Psr\Log\AbstractLogger;
use Stringable;

/**
 * Keeps what was logged, by level, without the `form-datalayer: ` prefix.
 */
final class TestLogger extends AbstractLogger
{
    /** @var array<string, list<string>> */
    public array $messages = [];

    /** @var list<array{level: string, message: string, context: array<mixed>}> */
    public array $records = [];

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $text = preg_replace('/^form-datalayer: /', '', (string) $message);
        $this->messages[(string) $level][] = $text;
        $this->records[] = ['level' => (string) $level, 'message' => $text, 'context' => $context];
    }

    /**
     * @return list<string>
     */
    public function of(string $level): array
    {
        return $this->messages[$level] ?? [];
    }
}
