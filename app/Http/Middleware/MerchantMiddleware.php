<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MerchantMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ($request->user()->role === 'merchant' || $request->user()->role === 'admin')) {
            return $next($request);
        }

        return response()->json(['message' => 'Unauthorized. Merchant access required.'], 403);
    }
}
