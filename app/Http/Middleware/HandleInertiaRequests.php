<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

use function App\Support\Desego\current_user;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        if (! current_user() && $request->cookie('DESEGO_REMEMBER')) {
            \App\Support\Desego\restore_remembered_session(\App\Support\Desego\desego_db());
        }

        return [...parent::share($request),
            'auth' => ['user' => fn () => current_user()],
            'csrfToken' => fn () => $request->session()->token(),
            'flash' => ['success' => fn () => $request->session()->get('success'), 'result' => fn () => $request->session()->get('result')],
        ];
    }
}
