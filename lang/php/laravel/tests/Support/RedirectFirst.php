<?php

declare(strict_types=1);

namespace Otaqq\FormDatalayer\Laravel\Tests\Support;

use Closure;
use Illuminate\Http\Request;

/**
 * A route middleware that redirects before the action runs (like a password confirmation).
 */
final class RedirectFirst
{
    public function handle(Request $request, Closure $next): mixed
    {
        return redirect('/admin/job-postings');
    }
}
