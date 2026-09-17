<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireInvoiceAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->header('X-DB-Webapp-Rechnungstool') !== '1') {
            abort(403, 'Für das Rechnungstool fehlt die erforderliche AD-Berechtigung.');
        }

        return $next($request);
    }
}
