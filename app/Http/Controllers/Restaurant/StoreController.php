<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Services\RestaurantPortal;
use App\Support\Desego\LegacyEndpoint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StoreController extends Controller
{
    public function index(RestaurantPortal $portal, LegacyEndpoint $legacy): Response
    {
        return Inertia::render('Restaurant/Store', ['catalog' => $portal->catalog(), 'lastOrder' => $legacy->call('storefront', [], ['view' => 'order'])['order'] ?? null]);
    }

    public function checkout(Request $request, RestaurantPortal $portal): RedirectResponse
    {
        $result = $portal->checkout($request->all());

        return back()->with('result', ['type' => 'order', ...$result])->with('success', 'Tu pedido ya esta en nuestro restaurante.');
    }

    public function quote(Request $request, LegacyEndpoint $legacy): RedirectResponse
    {
        $result = $legacy->call('storefront', [...$request->all(), 'action' => 'quote'], [], 'POST');

        return back()->with('result', ['type' => 'quote', ...$result]);
    }

    public function reserve(Request $request, RestaurantPortal $portal): RedirectResponse
    {
        return back()->with('result', ['type' => 'reservation', ...$portal->reserve($request->all())])->with('success', 'Recibimos tu solicitud de reserva.');
    }
}
