<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Services\GoogleIdentityTokenVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

use function App\Support\Desego\clear_remember_cookie;
use function App\Support\Desego\current_user;
use function App\Support\Desego\desego_db;
use function App\Support\Desego\establish_authenticated_session;
use function App\Support\Desego\issue_remember_token;

class GoogleAuthController extends Controller
{
    public function mobileChallenge(Request $request): JsonResponse
    {
        $clientId = (string) config('services.google.client_id');
        abort_unless($clientId !== '', 503, 'El inicio de sesión con Google no está configurado.');
        $nonce = Str::random(64);
        $request->session()->put('google_mobile_nonce', $nonce);
        $request->session()->put('google_mobile_expires', now()->addMinutes(5)->timestamp);

        return response()->json(['ok' => true, 'clientId' => $clientId, 'nonce' => $nonce])->header('Cache-Control', 'no-store');
    }

    public function mobileLogin(Request $request, GoogleIdentityTokenVerifier $verifier): JsonResponse
    {
        $data = $request->validate(['id_token' => 'required|string|max:10000']);
        $nonce = $request->session()->pull('google_mobile_nonce');
        $expires = $request->session()->pull('google_mobile_expires', 0);
        abort_unless(is_string($nonce) && $expires > now()->timestamp, 401, 'Vuelve a seleccionar tu cuenta de Google.');
        $profile = $verifier->verify($data['id_token'], $nonce);
        $existing = DB::table('users')->where('email', mb_strtolower($profile['email']))->first();
        if ($existing) {
            abort_unless(DB::table('roles')->where('id', $existing->role_id)->value('code') === 'customer', 403, 'La app está disponible para cuentas de clientes.');
            abort_if(! empty($existing->google_id) && $existing->google_id !== $profile['sub'], 401, 'La cuenta de Google no coincide con el cliente registrado.');
        }
        $user = $this->resolveGoogleUser($profile);
        clear_remember_cookie(desego_db());
        establish_authenticated_session((int) $user->id, $user->password_hash);
        $request->session()->regenerateToken();
        $request->session()->put('csrf_token', $request->session()->token());
        $request->session()->put('storefront_customer_id', DB::table('customers')->where('user_id', $user->id)->value('id'));

        if ($request->boolean('remember', true)) {
            issue_remember_token(desego_db(), (int) $user->id);
        }

        return response()->json(['ok' => true, 'user' => current_user(true), 'csrfToken' => $request->session()->token()])->header('Cache-Control', 'no-store');
    }

    public function redirect(Request $request): RedirectResponse
    {
        $clientId = (string) config('services.google.client_id');
        $redirectUri = (string) config('services.google.redirect_uri');

        if (empty($clientId) || empty($redirectUri)) {
            return redirect()->route('restaurant.login')->with('error', 'El inicio de sesión con Google no está configurado en el servidor.');
        }

        $state = Str::random(40);
        $request->session()->put('google_oauth_state', $state);

        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid profile email',
            'state' => $state,
            'prompt' => 'select_account',
        ]);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.$query);
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->has('error') || ! $request->has('code')) {
            return redirect()->route('restaurant.login')->with('error', 'El inicio de sesión con Google fue cancelado o denegado.');
        }

        $sessionState = $request->session()->pull('google_oauth_state');
        $requestState = (string) $request->input('state');

        if (empty($sessionState) || $sessionState !== $requestState) {
            return redirect()->route('restaurant.login')->with('error', 'La verificación de seguridad de Google expiró. Por favor intenta de nuevo.');
        }

        $tokenResponse = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => (string) config('services.google.client_id'),
            'client_secret' => (string) config('services.google.client_secret'),
            'code' => (string) $request->input('code'),
            'grant_type' => 'authorization_code',
            'redirect_uri' => (string) config('services.google.redirect_uri'),
        ]);

        if (! $tokenResponse->successful() || empty($tokenResponse->json('access_token'))) {
            return redirect()->route('restaurant.login')->with('error', 'No se pudo validar el código de autorización con Google.');
        }

        $accessToken = (string) $tokenResponse->json('access_token');
        $userInfoResponse = Http::withToken($accessToken)->get('https://www.googleapis.com/oauth2/v3/userinfo');

        if (! $userInfoResponse->successful() || empty($userInfoResponse->json('email'))) {
            return redirect()->route('restaurant.login')->with('error', 'No se pudo obtener el perfil de usuario de Google.');
        }

        $googleUser = $userInfoResponse->json();
        $user = $this->resolveGoogleUser($googleUser);
        $name = $user->name;

        clear_remember_cookie(desego_db());
        establish_authenticated_session((int) $user->id, $user->password_hash);
        $request->session()->regenerateToken();

        $customer = DB::table('customers')->where('user_id', $user->id)->first();
        if ($customer) {
            $request->session()->put('storefront_customer_id', $customer->id);
        }
        $request->session()->put('csrf_token', $request->session()->token());

        $roleCode = DB::table('roles')->where('id', $user->role_id)->value('code');
        $target = $roleCode === 'customer' ? '/cuenta' : '/admin';

        return redirect($target)->with('success', '¡Bienvenido(a) a DeSego, '.$name.'!');
    }

    /** @param array<string, mixed> $googleUser */
    private function resolveGoogleUser(array $googleUser): object
    {
        $email = mb_strtolower((string) $googleUser['email']);
        $googleId = (string) ($googleUser['sub'] ?? '');
        $name = trim((string) ($googleUser['name'] ?: 'Cliente DeSego'));
        $avatar = (string) ($googleUser['picture'] ?? '');

        return DB::transaction(function () use ($email, $googleId, $name, $avatar): object {
            $existing = DB::table('users')->where('email', $email)->first();

            if ($existing) {
                if ($existing->status !== 'active') {
                    abort(403, 'Tu cuenta de usuario se encuentra temporalmente desactivada.');
                }

                $update = ['updated_at' => now()];
                if (Schema::hasColumn('users', 'google_id') && empty($existing->google_id) && $googleId) {
                    $update['google_id'] = $googleId;
                }
                if (Schema::hasColumn('users', 'avatar_url') && empty($existing->avatar_url) && $avatar) {
                    $update['avatar_url'] = $avatar;
                }
                DB::table('users')->where('id', $existing->id)->update($update);
                $userId = $existing->id;
            } else {
                $roleId = DB::table('roles')->where('code', 'customer')->value('id')
                    ?? DB::table('roles')->insertGetId([
                        'code' => 'customer',
                        'name' => 'Cliente',
                        'description' => 'Cliente registrado en la tienda web',
                        'is_system' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                $insertData = [
                    'name' => $name,
                    'email' => $email,
                    'password_hash' => Hash::make(Str::random(32)),
                    'role_id' => $roleId,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (Schema::hasColumn('users', 'google_id') && $googleId) {
                    $insertData['google_id'] = $googleId;
                }
                if (Schema::hasColumn('users', 'avatar_url') && $avatar) {
                    $insertData['avatar_url'] = $avatar;
                }

                $userId = DB::table('users')->insertGetId($insertData);
            }

            // Ensure customer profile
            $customer = DB::table('customers')->where('user_id', $userId)->first();
            if (! $customer) {
                $byEmail = DB::table('customers')->whereNull('user_id')->where('email', $email)->first();
                if ($byEmail) {
                    DB::table('customers')->where('id', $byEmail->id)->update([
                        'user_id' => $userId,
                        'updated_at' => now(),
                    ]);
                    $customer = DB::table('customers')->where('id', $byEmail->id)->first();
                } else {
                    $customerId = DB::table('customers')->insertGetId([
                        'user_id' => $userId,
                        'full_name' => $name,
                        'email' => $email,
                        'document_type' => 'SIN_DOCUMENTO',
                        'status' => 'active',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    DB::table('customers')->where('id', $customerId)->update([
                        'customer_code' => 'CLI-'.str_pad((string) $customerId, 6, '0', STR_PAD_LEFT),
                    ]);
                    $customer = DB::table('customers')->where('id', $customerId)->first();
                }
            }

            // Ensure loyalty account exists
            DB::table('loyalty_accounts')->insertOrIgnore([
                'customer_id' => $customer->id,
                'points_balance' => 0,
                'lifetime_earned' => 0,
                'lifetime_redeemed' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return DB::table('users')->where('id', $userId)->first();
        });

    }
}
