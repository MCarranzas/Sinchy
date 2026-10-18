<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConfiarProxies
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($proxies = config('trustedproxy.proxies')) {
            $this->normalizarXForwardedFor($request);
            TrustProxies::at($proxies);
        }

        return $next($request);
    }

    private function normalizarXForwardedFor(Request $request): void
    {
        $header = $request->headers->get('X-Forwarded-For');

        if (! $header) {
            return;
        }

        $normalizado = preg_replace('/::ffff:(\d{1,3}(?:\.\d{1,3}){3})/', '$1', $header);

        if ($normalizado !== $header) {
            $request->headers->set('X-Forwarded-For', $normalizado);
        }
    }
}
