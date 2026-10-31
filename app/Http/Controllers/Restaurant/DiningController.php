<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Services\DiningService;
use App\Services\RestaurantPortal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DiningController extends Controller
{
    public function show(string $token, DiningService $dining, RestaurantPortal $portal): Response
    {
        return Inertia::render('Restaurant/Table', ['tablePreview' => $dining->preview($token), 'qrToken' => $token, 'catalog' => $this->withModels($portal->catalog())]);
    }

    public function payment(): Response
    {
        return Inertia::render('Restaurant/TablePayment');
    }

    /**
     * Adds the optional 3D model address (`config('dining.product_models')`, product id => .glb path or URL) to each dish.
     *
     * @param  array<string, mixed>  $catalog
     * @return array<string, mixed>
     */
    private function withModels(array $catalog): array
    {
        $models = (array) config('dining.product_models', []);
        $catalog['products'] = array_map(function (array $product) use ($models): array {
            $product['model_url'] = $models[$product['id']] ?? null;

            return $product;
        }, $catalog['products'] ?? []);

        return $catalog;
    }

    public function preview(string $token, DiningService $dining): JsonResponse
    {
        return response()->json($dining->preview($token));
    }

    public function join(Request $request, string $token, DiningService $dining): JsonResponse
    {
        return response()->json($dining->join($token, $request->all()));
    }

    public function state(Request $request, DiningService $dining): JsonResponse
    {
        return response()->json($dining->state((string) $request->header('X-Dining-Token')));
    }

    public function order(Request $request, DiningService $dining): JsonResponse
    {
        return response()->json($dining->order((string) $request->header('X-Dining-Token'), $request->all()));
    }

    public function attention(Request $request, DiningService $dining): JsonResponse
    {
        return response()->json($dining->requestAttention((string) $request->header('X-Dining-Token'), $request->all()));
    }

    public function floor(DiningService $dining): JsonResponse
    {
        return response()->json($dining->floor());
    }

    public function action(Request $request, DiningService $dining): JsonResponse
    {
        return response()->json($dining->staffAction($request->all()));
    }
}
