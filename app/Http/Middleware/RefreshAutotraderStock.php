<?php

namespace App\Http\Middleware;

use App\Models\Dealer;
use App\Services\Autotrader\StockRefresh;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RefreshAutotraderStock
{
    public function __construct(private StockRefresh $refresh) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldRefresh($request)) {
            $this->refresh->kick(Dealer::current());
        }

        return $response;
    }

    private function shouldRefresh(Request $request): bool
    {
        if (! $request->isMethod('GET') || $request->expectsJson() || $request->boolean('fragment')) {
            return false;
        }

        return ! $request->is('webhooks/*', 'up', 'css/*', 'js/*', 'images/*', 'favicon.ico');
    }
}
