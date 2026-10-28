<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StaffCan
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $staff = $request->user('staff');
        if (! $staff || ! $staff->can($ability)) {
            abort(403, 'You do not have permission to do that.');
        }

        return $next($request);
    }
}
