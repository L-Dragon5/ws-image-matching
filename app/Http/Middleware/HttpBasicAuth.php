<?php

namespace App\Http\Middleware;

use Closure;

class HttpBasicAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (app()->environment('staging', 'production')) {
            if ($request->getUser() != env('API_USERNAME') || $request->getPassword() != env('API_PASSWORD')) {
                $headers = ['WWW-Authenticate' => 'Basic'];
                return response('Unauthorized', 401, $headers);
            }
        }

        return $next($request);
    }
}
