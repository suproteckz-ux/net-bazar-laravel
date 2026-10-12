<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class KaspiApiAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = config('kaspi.api_token', '');

        if (strlen($token) < 32) {
            return response()->json(['error' => 'API token not configured'], 503);
        }

        if (!hash_equals('Bearer ' . $token, $request->header('Authorization', ''))) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
