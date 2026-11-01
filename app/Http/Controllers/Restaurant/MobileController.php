<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Services\RestaurantPortal;
use App\Support\Desego\LegacyEndpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use function App\Support\Desego\current_user;

class MobileController extends Controller
{
    public function health(): JsonResponse
    {
        DB::select('SELECT 1');

        return response()->json(['service' => 'desego', 'database' => 'connected']);
    }

    public function session(LegacyEndpoint $legacy): JsonResponse
    {
        $legacy->call('auth', [], ['action' => 'session']);
        $user = current_user();
        if (($user['role_code'] ?? null) !== 'customer') {
            $user = null;
        }

        return response()->json(['ok' => true, 'authenticated' => $user !== null, 'user' => $user, 'csrfToken' => session()->token()]);
    }

    public function catalog(RestaurantPortal $portal): JsonResponse
    {
        return response()->json($portal->catalog());
    }

    public function login(Request $request, RestaurantPortal $portal): JsonResponse
    {
        $result = $portal->login($request->all());
        if (($result['user']['role_code'] ?? null) !== 'customer') {
            $portal->logout();

            return response()->json(['ok' => false, 'message' => 'La aplicación está disponible para clientes. El equipo debe ingresar desde el panel web.'], 403);
        }

        return response()->json($result);
    }

    public function register(Request $request, RestaurantPortal $portal): JsonResponse
    {
        return response()->json($portal->register($request->all()), 201);
    }

    public function logout(RestaurantPortal $portal): JsonResponse
    {
        return response()->json($portal->logout());
    }

    public function checkout(Request $request, RestaurantPortal $portal): JsonResponse
    {
        return response()->json($portal->checkout($request->all()));
    }

    public function quote(Request $request, LegacyEndpoint $legacy): JsonResponse
    {
        return response()->json($legacy->call('storefront', [...$request->all(), 'action' => 'quote'], [], 'POST'));
    }

    public function account(RestaurantPortal $portal): JsonResponse
    {
        return response()->json(['ok' => true, ...$portal->account()]);
    }

    public function profile(Request $request, RestaurantPortal $portal): JsonResponse
    {
        return response()->json($portal->saveProfile($request->all()));
    }

    public function address(Request $request, RestaurantPortal $portal): JsonResponse
    {
        return response()->json($portal->saveAddress($request->all()));
    }

    public function deleteAddress(int $id, RestaurantPortal $portal): JsonResponse
    {
        return response()->json($portal->deleteAddress($id));
    }

    public function reserve(Request $request, RestaurantPortal $portal): JsonResponse
    {
        return response()->json($portal->reserve($request->all()));
    }

    public function cancelOrder(int $id, RestaurantPortal $portal): JsonResponse
    {
        return response()->json($portal->cancelOrder($id));
    }

    public function cancelReservation(int $id, RestaurantPortal $portal): JsonResponse
    {
        return response()->json($portal->cancelReservation($id));
    }

    public function dashboard(): JsonResponse
    {
        return response()->json(['ok' => false, 'message' => 'La administración se realiza desde el panel web.'], 403);
    }
}
