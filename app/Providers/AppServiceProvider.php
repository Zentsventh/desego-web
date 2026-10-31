<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->useBundledCertificateAuthorityWhenSystemOneIsBroken();
        $rateLimitResponse = static function (Request $request, array $headers): JsonResponse|RedirectResponse {
            $message = 'Demasiados intentos. Espera un minuto antes de volver a intentarlo.';

            return $request->is('api/*') || $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => $message], 429, $headers)
                : back()->withErrors(['email' => $message])->withHeaders($headers);
        };
        RateLimiter::for('restaurant-auth', function (Request $request) use ($rateLimitResponse): Limit {
            if ($request->isMethod('GET') || ($request->is('api/auth', 'api/auth.php') && ! in_array($request->query('action'), ['login', 'register'], true))) {
                return Limit::none();
            }
            $register = $request->is('registro', 'api/mobile/register') || $request->query('action') === 'register';

            return Limit::perMinute($register ? 5 : 10)->by(($register ? 'register:' : 'login:').$request->ip())
                ->response($rateLimitResponse);
        });
        RateLimiter::for('restaurant-password-reset', fn (Request $request): Limit => Limit::perMinute(5)->by('password-reset:'.$request->ip())->response($rateLimitResponse));
        if (PHP_SAPI !== 'cli-server') {
            Vite::prefetch(concurrency: 3);
        }
    }

    /**
     * Evita errores cURL 77 cuando php.ini apunta a un cacert.pem inexistente.
     */
    private function useBundledCertificateAuthorityWhenSystemOneIsBroken(): void
    {
        $configured = (string) (ini_get('curl.cainfo') ?: ini_get('openssl.cafile'));
        $bundled = resource_path('certificates/cacert.pem');

        if ($configured !== '' && ! is_file($configured) && is_file($bundled)) {
            Http::globalOptions(['verify' => $bundled]);
        }
    }
}
