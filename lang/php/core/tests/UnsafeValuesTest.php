<?php

declare(strict_types=1);

namespace Otaqq\FormDatalayer\Tests;

use Otaqq\FormDatalayer\Catalog;
use Otaqq\FormDatalayer\Output;
use Otaqq\FormDatalayer\Pusher;
use Otaqq\FormDatalayer\Settings;
use PHPUnit\Framework\TestCase;

/**
 * Values that JSON cannot carry are dropped, so a page always renders.
 */
final class UnsafeValuesTest extends TestCase
{
    public function test_invalid_utf8_and_non_finite_numbers_are_dropped_with_a_warning(): void
    {
        $catalog = Catalog::fromArray(['version' => 1, 'forms' => ['f' => ['label' => 'F', 'params' => [
            'q' => ['label' => 'Q', 'from' => 'q', 'default' => true],
            'n' => ['label' => 'N', 'from' => 'n', 'default' => true],
            'l' => ['label' => 'L', 'from' => 'l.*', 'default' => true],
        ]]]]);
        $logger = new TestLogger;

        $settings = Settings::fromArray(['version' => 1, 'forms' => ['f' => ['enabled' => true]]]);
        $push = (new Pusher($catalog, $settings, logger: $logger))->push('f', 'input', ['q' => "\xFF", 'n' => NAN, 'l' => ["ok", "\xC3", INF]]);

        $this->assertSame(['event' => 'f_input', 'form' => 'f', 'step' => 'input', 'l' => 'ok'], $push);
        $this->assertCount(4, $logger->of('warning'));
        $this->assertNotNull(Output::pushScript([$push]));
    }

    public function test_the_push_script_never_throws(): void
    {
        $this->assertStringContainsString('"t":"', (string) Output::pushScript([['event' => 'e', 't' => "\xFF"]]));
    }
}
