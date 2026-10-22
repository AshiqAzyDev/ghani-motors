<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StaffAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $staff = $request->user('staff');
        if (! $staff || ! $staff->is_active) {
            auth('staff')->logout();

            return redirect()->guest(route('dealer.login'));
        }

        return $next($request);
    }
}
