<?php

declare(strict_types=1);

namespace Otaqq\FormDatalayer\Laravel;

use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pushes `complete` on the next page when a route marked `complete` redirects without a failure
 * (spec/push.md#pages-from-routes). Failures are validation errors and the flash keys in
 * config form-datalayer.failures. Only the route's own response counts: a redirect from a route
 * middleware (password confirmation, …) runs no action and pushes nothing. Only the submitting
 * route counts: a safe method (GET, HEAD) is a complete page, which pushes when it renders; its
 * redirect means the page was not shown (a reload, a bookmark, a bot) and pushes nothing.
 * Added to the web group.
 */
final class CompleteOnRedirect
{
    /** Set on the request when the route's action has answered (Router's ResponsePrepared, see the service provider). */
    public const RAN = 'form-datalayer.action-ran';

    public function __construct(private readonly Config $config) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $route = $request->route();
        $meta = $route instanceof \Illuminate\Routing\Route ? RoutePages::meta($route) : null;
        if ($meta === null || $meta['step'] !== 'complete' || $request->isMethodSafe() || ! $response->isRedirection() || ! $request->hasSession()
            || $request->attributes->get(self::RAN) !== true) {
            return $response;
        }
        $flashed = (array) $request->session()->get('_flash.new', []);
        $failures = ['errors', ...(array) $this->config->get('form-datalayer.failures', [])];
        if (array_intersect($failures, $flashed) !== []) {
            return $response;
        }

        try {
            $datalayer = app(FormDatalayer::class);
            $datalayer->flash($meta['form'], 'complete', [], $datalayer->mode() ?? $meta['mode']);
        } catch (\Throwable $e) {
            // Measuring never breaks a page: the action has already run. form-datalayer:check reports catalog errors
            logger()->error("form-datalayer: complete not pushed: {$e->getMessage()}");
        }

        return $response;
    }
}
