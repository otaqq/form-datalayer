<?php

declare(strict_types=1);

namespace Otaqq\FormDatalayer;

/**
 * What goes into the page for Google Tag Manager (spec/output.md). Every way of writing pushes is kept here,
 * so that other tools can be added in one place.
 */
final class Output
{
    /** `<`, `>`, `&`, `'`, `"` in strings, U+2028 and U+2029 escaped; `/` and non-ASCII written as they are. */
    private const JSON = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR;

    public static function isContainerId(string $id): bool
    {
        return preg_match('/^GTM-[A-Z0-9]+$/D', $id) === 1;
    }

    public static function isVariable(string $variable): bool
    {
        return preg_match('/^[A-Za-z_$][A-Za-z0-9_$]*$/D', $variable) === 1;
    }

    /**
     * The JavaScript that pushes, in order, or null when there is nothing to push.
     *
     * @param  list<array<string, mixed>>  $pushes
     */
    public static function pushScript(array $pushes, string $variable = 'dataLayer'): ?string
    {
        if ($pushes === []) {
            return null;
        }
        $script = "window.{$variable}=window.{$variable}||[];";
        foreach ($pushes as $push) {
            $json = json_encode((object) $push, self::JSON);
            if ($json === false) {
                continue;
            }
            $script .= "window.{$variable}.push({$json});";
        }

        return $script;
    }

    /**
     * For `<head>`: the push script, then Google's snippet. Empty when the container id is empty.
     * The container id and the variable must pass isContainerId() and isVariable().
     *
     * @param  list<array<string, mixed>>  $pushes
     */
    public static function head(string $containerId, array $pushes = [], string $variable = 'dataLayer', ?string $nonce = null): string
    {
        if (! self::isContainerId($containerId) || ! self::isVariable($variable)) {
            return '';
        }
        $open = self::open($nonce);
        $html = '';
        $script = self::pushScript($pushes, $variable);
        if ($script !== null) {
            $html .= "{$open}{$script}</script>\n";
        }

        return $html.$open."(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','{$variable}','{$containerId}');</script>\n";
    }

    /**
     * For right after `<body>`: the `<noscript>` iframe. Empty when the container id is empty.
     */
    public static function body(string $containerId): string
    {
        if (! self::isContainerId($containerId)) {
            return '';
        }

        return "<noscript><iframe src=\"https://www.googletagmanager.com/ns.html?id={$containerId}\" height=\"0\" width=\"0\" style=\"display:none;visibility:hidden\"></iframe></noscript>\n";
    }

    private static function open(?string $nonce): string
    {
        return $nonce === null || $nonce === ''
            ? '<script>'
            : '<script nonce="'.htmlspecialchars($nonce, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'">';
    }
}
