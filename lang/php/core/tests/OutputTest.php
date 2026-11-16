<?php

declare(strict_types=1);

namespace Otaqq\FormDatalayer\Tests;

use Otaqq\FormDatalayer\Output;
use PHPUnit\Framework\TestCase;

final class OutputTest extends TestCase
{
    public function test_head_writes_the_pushes_then_the_snippet_with_the_nonce(): void
    {
        $head = Output::head('GTM-AB12', [['event' => 'e']], 'dataLayer', 'n"1');

        $this->assertSame(
            "<script nonce=\"n&quot;1\">window.dataLayer=window.dataLayer||[];window.dataLayer.push({\"event\":\"e\"});</script>\n"
            ."<script nonce=\"n&quot;1\">(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','GTM-AB12');</script>\n",
            $head,
        );
    }

    public function test_head_without_pushes_writes_only_the_snippet(): void
    {
        $head = Output::head('GTM-AB12');

        $this->assertSame(1, substr_count($head, '<script>'));
        $this->assertStringContainsString("'dataLayer','GTM-AB12'", $head);
    }

    public function test_an_empty_or_broken_container_id_or_variable_writes_nothing(): void
    {
        $this->assertSame('', Output::head('', [['event' => 'e']]));
        $this->assertSame('', Output::head("GTM-A');alert(1);//", [['event' => 'e']]));
        $this->assertSame('', Output::head('GTM-AB12', [['event' => 'e']], 'a.b'));
        $this->assertSame('', Output::body(''));
    }

    public function test_body_writes_the_noscript_iframe(): void
    {
        $this->assertSame(
            "<noscript><iframe src=\"https://www.googletagmanager.com/ns.html?id=GTM-AB12\" height=\"0\" width=\"0\" style=\"display:none;visibility:hidden\"></iframe></noscript>\n",
            Output::body('GTM-AB12'),
        );
    }

    public function test_double_quotes_inside_strings_are_escaped(): void
    {
        $this->assertSame('window.dataLayer=window.dataLayer||[];window.dataLayer.push({"event":"e","t":"\u0022x\u0022"});', Output::pushScript([['event' => 'e', 't' => '"x"']]));
    }
}
