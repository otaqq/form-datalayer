<?php

declare(strict_types=1);

namespace Otaqq\FormDatalayer\Laravel;

use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ViewErrorBag;
use Otaqq\FormDatalayer\Catalog;
use Otaqq\FormDatalayer\Lookup;
use Otaqq\FormDatalayer\Names;
use Otaqq\FormDatalayer\Output;
use Otaqq\FormDatalayer\Pusher;
use Otaqq\FormDatalayer\Settings;
use Psr\Log\LoggerInterface;

/**
 * The pushes of one request: the ones flashed by the previous request, the step of the current page
 * (from its route name), and the ones the app adds. Bound per request (scoped).
 */
final class FormDatalayer
{
    /** Pushes for the next request; read there, so a request never re-reads what it flashes itself. */
    public const FLASH_NEXT = 'form-datalayer.pushes';

    private ?Catalog $catalog = null;

    private ?Settings $settings = null;

    private ?Pusher $pusher = null;

    /** @var array<mixed> */
    private array $context = [];

    /** @var list<array<string, mixed>> */
    private array $pushes = [];

    /** @var list<array<string, mixed>> Flashed by this request, for the next one */
    private array $flashed = [];

    /** @var list<array<string, mixed>>|null Flashed by the previous request, read before this one flashes */
    private ?array $arrived = null;

    private bool $pagePushed = false;

    public function __construct(
        private readonly Config $config,
        private readonly Request $request,
        private readonly Auth $auth,
        private readonly Lookup $lookup,
        private readonly LoggerInterface $logger,
    ) {}

    public function catalog(): Catalog
    {
        return $this->catalog ??= Catalog::fromFile((string) $this->config->get('form-datalayer.catalog'));
    }

    public function settings(): Settings
    {
        return $this->settings ??= Settings::fromFile((string) $this->config->get('form-datalayer.settings'));
    }

    /**
     * Adds values to the context of the page's own push (the record shown, counts, …).
     *
     * @param  array<mixed>  $context
     */
    public function with(array $context): self
    {
        $this->context = self::merge($this->context, self::plain($context));

        return $this;
    }

    /**
     * Pushes a step on the page being rendered.
     *
     * @param  array<mixed>  $context
     */
    public function push(string $form, string $step, array $context = [], ?string $mode = null): void
    {
        if ($this->pagePushed) {
            $this->logger->warning("form-datalayer: {$form}.{$step} pushed after the pushes were written; it is not sent");

            return;
        }
        $push = $this->pusher()->push($form, $step, $this->contextWith($context), $mode);
        if ($push !== null) {
            $this->pushes[] = $push;
        }
    }

    /**
     * Pushes a step on the next page, for a step shown after a redirect (a form saved, then a list).
     * The push is built now, so the values are those of this request.
     *
     * @param  array<mixed>  $context
     */
    public function flash(string $form, string $step, array $context = [], ?string $mode = null): void
    {
        $push = $this->pusher()->push($form, $step, $this->contextWith($context), $mode);
        if ($push !== null && $this->request->hasSession()) {
            $this->arrived();
            $this->flashed[] = $push;
            $this->request->session()->flash(self::FLASH_NEXT, $this->flashed);
        }
    }

    /**
     * Every push of the page, in order: flashed, the page's own step, then the ones added.
     *
     * @return list<array<string, mixed>>
     */
    public function pushes(): array
    {
        if (! $this->pagePushed) {
            $this->pagePushed = true;
            $page = $this->pagePush();
            if ($page !== null) {
                array_unshift($this->pushes, $page);
            }
            $this->pushes = [...$this->arrived(), ...$this->pushes];
        }

        return $this->pushes;
    }

    /**
     * For `<head>` (Blade: @formDatalayerHead('name')). Empty without a container.
     */
    public function head(string $container = 'default'): string
    {
        $id = $this->containerId($container);
        $variable = $this->variable();
        if ($id === '' || $variable === null) {
            return '';
        }

        try {
            return Output::head($id, $this->pushes(), $variable, Vite::cspNonce());
        } catch (\Throwable $e) {
            // Measuring never breaks a page
            $this->logger->error("form-datalayer: nothing is written: {$e->getMessage()}");

            return '';
        }
    }

    /**
     * For right after `<body>` (Blade: @formDatalayerBody('name')).
     */
    public function body(string $container = 'default'): string
    {
        $id = $this->containerId($container);

        return $id === '' || $this->variable() === null ? '' : Output::body($id);
    }

    private function variable(): ?string
    {
        $variable = (string) $this->config->get('form-datalayer.variable', 'dataLayer');
        if (! Output::isVariable($variable)) {
            $this->logger->error("form-datalayer: invalid variable {$variable}");

            return null;
        }

        return $variable;
    }

    /**
     * The pushes flashed by the previous request.
     *
     * @return list<array<string, mixed>>
     */
    private function arrived(): array
    {
        if ($this->arrived === null) {
            $arrived = $this->request->hasSession() ? $this->request->session()->get(self::FLASH_NEXT, []) : [];
            $this->arrived = is_array($arrived) ? array_values(array_filter($arrived, 'is_array')) : [];
        }

        return $this->arrived;
    }

    private function containerId(string $container): string
    {
        $id = (string) $this->config->get("form-datalayer.containers.{$container}", '');
        if ($id !== '' && ! Output::isContainerId($id)) {
            $this->logger->error("form-datalayer: invalid container id for {$container}");

            return '';
        }

        return $id;
    }

    /**
     * The step of the current page from its route name; `error` when an input page comes back with validation errors.
     *
     * @return array<string, mixed>|null
     */
    private function pagePush(): ?array
    {
        $name = $this->request->route()?->getName();
        $page = $name === null ? null : $this->catalog()->page($name);
        if ($page === null) {
            return null;
        }
        $error = $page->step === 'input' && $this->hasErrors();
        $input = $error ? (array) $this->request->old() : $this->request->all();

        return $this->pusher()->push($page->form, $error ? 'error' : $page->step, ['input' => $input] + $this->baseContext(), $page->mode);
    }

    /**
     * The context of push() and flash(): their own values first, then the request input and with().
     *
     * @param  array<mixed>  $context
     * @return array<mixed>
     */
    private function contextWith(array $context): array
    {
        return self::merge(['input' => $this->request->all()] + $this->baseContext(), self::plain($context));
    }

    private function hasErrors(): bool
    {
        if (! $this->request->hasSession()) {
            return false;
        }
        $errors = $this->request->session()->get('errors');
        if (! $errors instanceof ViewErrorBag) {
            return false;
        }
        foreach ($errors->getBags() as $bag) {
            if ($bag->any()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<mixed>
     */
    private function baseContext(): array
    {
        $context = $this->context;
        $user = $this->auth->guard()->user();
        $attributes = (array) $this->config->get('form-datalayer.user', []);
        if ($user !== null && $attributes !== [] && ! isset($context['user'])) {
            $context['user'] = [];
            foreach ($attributes as $attribute) {
                $context['user'][$attribute] = self::plain($user->{$attribute} ?? null);
            }
        }

        return $context;
    }

    /**
     * Models, collections and enums as arrays and plain values, so that paths can follow them.
     */
    private static function plain(mixed $value): mixed
    {
        return match (true) {
            $value instanceof \BackedEnum => $value->value,
            $value instanceof \UnitEnum => $value->name,
            $value instanceof \Illuminate\Contracts\Support\Arrayable => self::plain($value->toArray()),
            $value instanceof \DateTimeInterface => $value->format(DATE_ATOM),
            // Integer keys with gaps (a filtered collection) are still a list
            is_array($value) => self::relist(array_map(self::plain(...), $value)),
            default => $value,
        };
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private static function relist(array $value): array
    {
        return $value !== [] && ! array_is_list($value) && array_filter(array_keys($value), 'is_string') === [] ? array_values($value) : $value;
    }

    /**
     * Maps are merged key by key; lists and plain values are replaced.
     *
     * @param  array<mixed>  $base
     * @param  array<mixed>  $over
     * @return array<mixed>
     */
    private static function merge(array $base, array $over): array
    {
        if (array_is_list($over) && $over !== []) {
            return $over;
        }
        foreach ($over as $key => $value) {
            $base[$key] = is_array($value) && is_array($base[$key] ?? null) && ! array_is_list($base[$key]) && ($value === [] || ! array_is_list($value))
                ? self::merge($base[$key], $value)
                : $value;
        }

        return $base;
    }

    private function pusher(): Pusher
    {
        return $this->pusher ??= new Pusher($this->catalog(), $this->settings(), $this->lookup, $this->logger);
    }

    /**
     * The default event name of a step (for the settings screen).
     */
    public static function defaultEventName(string $form, string $step): string
    {
        return Names::defaultEventName($form, $step);
    }
}
