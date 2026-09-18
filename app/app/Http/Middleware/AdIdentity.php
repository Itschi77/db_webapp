<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class AdIdentity
{
    public function handle(Request $request, Closure $next): Response
    {
        $principal = trim((string) $request->header('X-Remote-User', ''));
        $username = $principal;

        if (str_contains($username, '\\')) {
            $username = substr($username, strrpos($username, '\\') + 1);
        }
        if (str_contains($username, '@')) {
            $username = strstr($username, '@', true) ?: $username;
        }

        $canInvoiceTool = $request->header('X-DB-Webapp-Rechnungstool') === '1';
        $canAdmin = $request->header('X-DB-Webapp-Admin') === '1';

        $request->attributes->set('ad_principal', $principal);
        $request->attributes->set('ad_username', $username);
        $request->attributes->set('ad_can_invoice_tool', $canInvoiceTool);
        $request->attributes->set('ad_can_admin', $canAdmin);

        View::share('adPrincipal', $principal);
        View::share('adUsername', $username);
        View::share('adCanInvoiceTool', $canInvoiceTool);
        View::share('adCanAdmin', $canAdmin);

        return $next($request);
    }
}
