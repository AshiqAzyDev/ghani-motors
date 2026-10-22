<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StaffCanMutate
{
    public function handle(Request $request, Closure $next): Response
    {
        $staff = $request->user('staff');
        if (! $staff || ! $staff->canMutate()) {
            abort(403, 'Your role can view records but cannot change them.');
        }

        return $next($request);
    }
}
