<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use function App\Support\Desego\current_user;

class RequireRestaurantStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = current_user();
        if (! $user) {
            return redirect()->route('restaurant.login', ['staff' => 1]);
        }
        abort_if($user['role_code'] === 'customer' || empty($user['permissions']), 403);

        return $next($request);
    }
}
