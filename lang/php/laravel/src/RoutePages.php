<?php

declare(strict_types=1);

namespace Otaqq\FormDatalayer\Laravel;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Otaqq\FormDatalayer\Page;

/**
 * Pages from route metadata (spec/push.md#pages-from-routes): `->dataLayer('admin.prefecture', 'input', mode: 'create')`.
 * The metadata lives in the route action, so it survives `route:cache`.
 */
final class RoutePages
{
    public const ACTION = 'form-datalayer';

    /**
     * Adds the `dataLayer()` macro to routes.
     */
    public static function register(): void
    {
        Route::macro('dataLayer', function (string $form, string $step, ?string $mode = null): Route {
            /** @var Route $this */
            $this->action[RoutePages::ACTION] = ['form' => $form, 'step' => $step, 'mode' => $mode];

            return $this;
        });
    }

    /**
     * Every page the routes declare.
     *
     * @return list<Page>
     */
    public static function all(Router $router): array
    {
        $pages = [];
        foreach ($router->getRoutes()->getRoutes() as $route) {
            $meta = self::meta($route);
            if ($meta !== null) {
                $pages[] = new Page(self::id($route), $meta['form'], $meta['step'], $meta['mode']);
            }
        }

        return $pages;
    }

    /**
     * The page id of a route: its name, or its method, domain and URI. A name that is only the prefix of a
     * named group (`admin.`, ending with a dot) is no name.
     */
    public static function id(Route $route): string
    {
        $name = $route->getName();
        if ($name !== null && $name !== '' && ! str_ends_with($name, '.')) {
            return $name;
        }
        $domain = $route->getDomain();

        return $route->methods()[0].' '.($domain === null || $domain === '' ? '' : $domain.'/').$route->uri();
    }

    /**
     * @return array{form: string, step: string, mode: string|null}|null
     */
    public static function meta(Route $route): ?array
    {
        $meta = $route->getAction(self::ACTION);

        return is_array($meta) && is_string($meta['form'] ?? null) && is_string($meta['step'] ?? null)
            ? ['form' => $meta['form'], 'step' => $meta['step'], 'mode' => is_string($meta['mode'] ?? null) ? $meta['mode'] : null]
            : null;
    }
}
