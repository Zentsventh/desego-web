<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use function App\Support\Desego\current_user;

class DesegoHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->session()->put('csrf_token', $request->session()->token());
        if ($request->session()->has('user_id')) {
            current_user();
        }
        $response = $next($request);
        $response->headers->add([
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'Cache-Control' => 'no-store, max-age=0',
        ]);
        foreach ($request->attributes->get('desego_headers', []) as $name => $value) {
            $response->headers->set($name, $value);
        }
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=15552000');
        }

        return $response;
    }
}
