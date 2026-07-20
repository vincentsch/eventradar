<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventSearchIndexing
{
    /**
     * Mark assessment and staging responses as unavailable to search engines.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (config('app.prevent_indexing')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        }

        return $response;
    }
}
