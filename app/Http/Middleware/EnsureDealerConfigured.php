<?php

namespace App\Http\Middleware;

use App\Models\Dealer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureDealerConfigured
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldBypass($request)) {
            return $next($request);
        }

        try {
            if (Schema::hasTable('dealers') && Dealer::query()->exists()) {
                return $next($request);
            }
        } catch (\Throwable) {
            return $next($request);
        }

        return redirect()->route('setup.dealer');
    }

    private function shouldBypass(Request $request): bool
    {
        if ($request->routeIs('setup.*')) {
            return true;
        }

        return $request->is('up', 'webhooks/*', 'setup', 'setup/*', 'robots.txt', 'sitemap.xml');
    }
}
