<?php

declare(strict_types=1);

namespace Otaqq\FormDatalayer;

/**
 * The naming rules of form keys, parameter names and event names (spec/catalog.md, spec/push.md#event-names).
 */
final class Names
{
    public const FORM_STEPS = ['input', 'confirm', 'complete', 'error'];

    /** The steps pages can show (`error` has the pages of `input`). */
    public const PAGE_STEPS = ['input', 'confirm', 'complete'];

    public const MODES = ['create', 'edit'];

    /** Sent with every push, so not available as parameter names. */
    public const RESERVED_PARAMS = ['event', 'form', 'step', 'mode'];

    private const RESERVED_PREFIX = '/^(?:google_|ga_|firebase_)/';

    public static function isFormKey(mixed $key): bool
    {
        return is_string($key) && strlen($key) <= 64 && preg_match('/^[a-z][a-z0-9]*(?:[.-][a-z0-9]+)*$/D', $key) === 1;
    }

    /**
     * A GA4 event name: an ASCII letter, then letters, digits or `_`, at most 40, no reserved prefix.
     */
    public static function isEventName(mixed $name): bool
    {
        return is_string($name) && strlen($name) <= 40
            && preg_match('/^[A-Za-z][A-Za-z0-9_]*$/D', $name) === 1
            && preg_match(self::RESERVED_PREFIX, $name) !== 1;
    }

    /**
     * A container name the app declares (`front`, `admin`): lower-case ASCII letters, digits, `_` and `-`.
     */
    public static function isContainerName(mixed $name): bool
    {
        return is_string($name) && strlen($name) <= 40 && preg_match('/^[a-z][a-z0-9_-]*$/D', $name) === 1;
    }

    /**
     * The pattern of event and parameter names alone (no reserved prefixes): what a settings file may name.
     */
    public static function isName(mixed $name): bool
    {
        return is_string($name) && strlen($name) <= 40 && preg_match('/^[A-Za-z][A-Za-z0-9_]*$/D', $name) === 1;
    }

    /**
     * A parameter name in the catalog: the event name rules, and none of the names sent with every push.
     */
    public static function isParamName(mixed $name): bool
    {
        return self::isEventName($name) && ! in_array($name, self::RESERVED_PARAMS, true);
    }

    /**
     * `<key>_<step>`, with `.` and `-` replaced by `_`.
     */
    public static function defaultEventName(string $formKey, string $step): string
    {
        return str_replace(['.', '-'], '_', $formKey)."_{$step}";
    }

    /**
     * The group of a form: the first word of its key (`front` for `front.entry`).
     */
    public static function group(string $formKey): string
    {
        return (string) preg_split('/[.-]/', $formKey, 2)[0];
    }
}
