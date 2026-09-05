<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequestTiming
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $start = microtime(true);

        $response = $next($request);

        $time = (microtime(true) - $start) * 1000;

        logger()->info('Total Laravel request time', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'time_ms' => round($time, 2),
        ]);

        return $response;
    }
}