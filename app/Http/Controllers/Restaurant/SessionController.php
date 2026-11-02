<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Services\RestaurantPortal;
use App\Support\Desego\LegacyEndpoint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function App\Support\Desego\current_user;

class SessionController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if ($request->boolean('staff')) {
            return redirect()->route('admin.home');
        }

        return Inertia::render('Restaurant/Auth', ['register' => false, 'staff' => false]);
    }

    public function registration(): Response
    {
        return Inertia::render('Restaurant/Auth', ['register' => true, 'staff' => false]);
    }

    public function login(Request $request, RestaurantPortal $portal): RedirectResponse
    {
        return $this->authenticate($request, $portal, false);
    }

    public function staffLogin(Request $request, RestaurantPortal $portal): RedirectResponse
    {
        return $this->authenticate($request, $portal, true);
    }

    private function authenticate(Request $request, RestaurantPortal $portal, bool $staff): RedirectResponse
    {
        $data = $request->validate(['email' => 'required|email|max:190', 'password' => 'required|string|max:4096', 'remember' => 'boolean']);
        try {
            $portal->login($data);
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 401) {
                throw $exception;
            }
            throw ValidationException::withMessages(['email' => 'El correo o la contraseña no son correctos, o la cuenta está temporalmente bloqueada.']);
        }
        $user = current_user();

        $isStaff = ($user['role_code'] ?? null) !== 'customer'
            && ! empty($user['permissions'] ?? []);
        if ($staff !== $isStaff) {
            $portal->logout();

            throw ValidationException::withMessages([
                'email' => $staff
                    ? 'Esta cuenta no tiene acceso al panel del equipo. Ingresa desde el acceso de clientes.'
                    : 'Esta cuenta pertenece al equipo DESEGO. Ingresa desde el acceso del personal.',
            ]);
        }

        return redirect($staff ? '/admin' : '/cuenta');
    }

    public function forgotPassword(): Response
    {
        return Inertia::render('Restaurant/Auth', ['forgot' => true]);
    }

    public function resetPasswordForm(Request $request): Response
    {
        return Inertia::render('Restaurant/Auth', ['reset' => true, 'token' => (string) $request->query('token', '')]);
    }

    public function sendResetLink(Request $request, LegacyEndpoint $legacy): RedirectResponse
    {
        $data = $request->validate(['email' => 'required|email|max:190']);
        $result = $legacy->call('password-reset', $data, ['action' => 'request'], 'POST');

        return back()->with('success', $result['message']);
    }

    public function resetPassword(Request $request, LegacyEndpoint $legacy): RedirectResponse
    {
        $data = $request->validate(['token' => 'required|string|size:64|regex:/\A[a-f0-9]{64}\z/', 'password' => 'required|string|min:12|max:4096|confirmed', 'password_confirmation' => 'required|string']);
        $result = $legacy->call('password-reset', ['token' => $data['token'], 'password' => $data['password'], 'confirmPassword' => $data['password_confirmation']], ['action' => 'complete'], 'POST');

        return redirect()->route('restaurant.login')->with('success', $result['message']);
    }

    public function register(Request $request, RestaurantPortal $portal): RedirectResponse
    {
        $portal->register($request->all());

        return redirect('/cuenta')->with('success', 'Tu cuenta ya esta lista.');
    }

    public function logout(RestaurantPortal $portal): RedirectResponse
    {
        $user = current_user();
        $wasStaff = ($user['role_code'] ?? null) !== 'customer';
        $portal->logout();

        return redirect($wasStaff ? route('admin.home') : '/');
    }
}
