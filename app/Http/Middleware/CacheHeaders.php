<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Add HTTP caching headers (Cache-Control, ETag) to responses.
 *
 * For authenticated HTML pages: private, short-lived caching with ETag for revalidation.
 * For static/public assets: long-lived caching is handled by the web server / Vite hashing.
 *
 * This middleware only applies to successful GET responses that produce HTML content.
 */
class CacheHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only process GET requests with successful (2xx) responses
        if (!$request->isMethod('GET') || !$response->isSuccessful()) {
            return $response;
        }

        // Skip JSON / API responses -- they should not be browser-cached
        if ($response->headers->get('Content-Type') &&
            str_contains($response->headers->get('Content-Type'), 'application/json')) {
            return $response;
        }

        // Skip if Cache-Control was already explicitly set
        if ($response->headers->has('Cache-Control') &&
            $response->headers->get('Cache-Control') !== 'no-cache, private') {
            return $response;
        }

        $content = $response->getContent();

        if ($content === false || $content === '') {
            return $response;
        }

        // Generate ETag from response content
        $etag = '"' . md5($content) . '"';
        $response->headers->set('ETag', $etag);

        // Private caching: content varies per user session, so it must not be stored in shared caches.
        // max-age=0 forces browsers to always revalidate, but they can use the ETag to
        // receive a 304 Not Modified instead of re-downloading the full page.
        $response->headers->set('Cache-Control', 'private, no-cache, must-revalidate');

        // If the client sends a matching ETag, return 304 Not Modified
        $ifNoneMatch = $request->headers->get('If-None-Match');
        if ($ifNoneMatch && $ifNoneMatch === $etag) {
            $response->setStatusCode(304);
            $response->setContent('');
        }

        return $response;
    }
}
