<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAdminAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->header('X-DB-Webapp-Admin') !== '1') {
            abort(403, 'Für den Adminbereich fehlt die erforderliche AD-Berechtigung.');
        }

        return $next($request);
    }
}
