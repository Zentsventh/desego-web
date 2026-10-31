<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Services\RestaurantPortal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

use function App\Support\Desego\current_user;

class CustomerController extends Controller
{
    public function index(RestaurantPortal $portal): Response|RedirectResponse
    {
        if (! current_user()) {
            return redirect()->route('restaurant.login');
        }

        return Inertia::render('Restaurant/Account', ['account' => $portal->account()]);
    }

    public function profile(Request $request, RestaurantPortal $portal): RedirectResponse
    {
        $portal->saveProfile($request->all());

        return back()->with('success', 'Perfil actualizado.');
    }

    public function address(Request $request, RestaurantPortal $portal): RedirectResponse
    {
        $portal->saveAddress($request->all());

        return back()->with('success', 'Direccion guardada.');
    }

    public function deleteAddress(int $id, RestaurantPortal $portal): RedirectResponse
    {
        $portal->deleteAddress($id);

        return back()->with('success', 'Direccion eliminada.');
    }

    public function cancelOrder(int $id, RestaurantPortal $portal): RedirectResponse
    {
        $portal->cancelOrder($id);

        return back()->with('success', 'Pedido cancelado.');
    }

    public function cancelReservation(int $id, RestaurantPortal $portal): RedirectResponse
    {
        $portal->cancelReservation($id);

        return back()->with('success', 'Reserva cancelada.');
    }
}
