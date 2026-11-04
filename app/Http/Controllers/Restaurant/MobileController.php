<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Services\RestaurantPortal;
use App\Support\Desego\LegacyEndpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use function App\Support\Desego\current_user;
use function App\Support\Desego\desego_db;
use function App\Support\Desego\issue_remember_token;

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

    public function sync(RestaurantPortal $portal): JsonResponse
    {
        $user = current_user();
        $authenticated = ($user['role_code'] ?? null) === 'customer';

        return response()->json([
            'ok' => true,
            'authenticated' => $authenticated,
            'user' => $authenticated ? $user : null,
            'catalog' => $portal->catalog(),
            'account' => $authenticated ? $portal->account() : null,
            'server_time' => now()->toIso8601String(),
            'csrfToken' => session()->token(),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function favorite(Request $request, int $id, RestaurantPortal $portal): JsonResponse
    {
        $customer = $portal->customer();
        $data = $request->validate(['favorited' => 'required|boolean']);
        abort_unless(DB::table('products')->where('id', $id)->exists(), 404);
        $visitorKey = hash('sha256', 'customer_'.$customer->id);

        DB::transaction(function () use ($customer, $data, $visitorKey, $id): void {
            DB::table('customers')->where('id', $customer->id)->lockForUpdate()->first();
            $query = DB::table('storefront_favorites')->where('visitor_key', $visitorKey)->where('product_id', $id);
            if ($data['favorited']) {
                if (! $query->exists()) {
                    DB::table('storefront_favorites')->insert(['visitor_key' => $visitorKey, 'product_id' => $id, 'created_at' => now()]);
                }
            } else {
                $query->delete();
            }
        });

        return response()->json(['ok' => true, 'favorited' => (bool) $data['favorited']]);
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
        $result = $portal->register($request->all());

        if ($request->boolean('remember', true) && isset($result['user']['id'])) {
            issue_remember_token(desego_db(), (int) $result['user']['id']);
        }

        return response()->json($result, 201);
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

    public function defaultAddress(int $id, RestaurantPortal $portal): JsonResponse
    {
        return response()->json($portal->setDefaultAddress($id));
    }

    public function deleteAddress(int $id, RestaurantPortal $portal): JsonResponse
    {
        return response()->json($portal->deleteAddress($id));
    }

    public function changePassword(Request $request, RestaurantPortal $portal): JsonResponse
    {
        return response()->json($portal->changePassword($request->all()));
    }

    public function redeemReward(Request $request, RestaurantPortal $portal): JsonResponse
    {
        $rewardId = (int) $request->input('reward_id');

        return response()->json($portal->redeemReward($rewardId));
    }

    public function submitReview(Request $request, RestaurantPortal $portal): JsonResponse
    {
        return response()->json($portal->submitReview($request->all()));
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
