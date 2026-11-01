<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Services\RestaurantPortal;
use Illuminate\Contracts\View\View;
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
        $portal->saveProfile($request->all(), $request->file('avatar'));

        return back()->with('success', 'Perfil actualizado.');
    }

    public function password(Request $request, RestaurantPortal $portal): RedirectResponse
    {
        $result = $portal->changePassword($request->all());

        return back()->with('success', $result['message']);
    }

    public function address(Request $request, RestaurantPortal $portal): RedirectResponse
    {
        $portal->saveAddress($request->all());

        return back()->with('success', 'Direccion guardada.');
    }

    public function defaultAddress(int $id, RestaurantPortal $portal): RedirectResponse
    {
        $result = $portal->setDefaultAddress($id);

        return back()->with('success', $result['message']);
    }

    public function deleteAddress(int $id, RestaurantPortal $portal): RedirectResponse
    {
        $portal->deleteAddress($id);

        return back()->with('success', 'Dirección eliminada.');
    }

    public function redeemReward(Request $request, RestaurantPortal $portal): RedirectResponse
    {
        $data = $request->validate(['reward_id' => 'required|integer|min:1']);
        $result = $portal->redeemReward((int) $data['reward_id']);

        return back()->with('success', $result['message'])->with('result', $result);
    }

    public function cancelOrder(int $id, RestaurantPortal $portal): RedirectResponse
    {
        $portal->cancelOrder($id);

        return back()->with('success', 'Pedido cancelado.');
    }

    public function modifyOrder(int $id, Request $request, RestaurantPortal $portal): RedirectResponse
    {
        $result = $portal->modifyPendingOrder($id, $request->all());

        return back()->with('success', $result['message']);
    }

    public function receipt(int $id, RestaurantPortal $portal): View|RedirectResponse
    {
        if (! current_user()) {
            return redirect()->route('restaurant.login');
        }

        return view('receipt', $portal->orderReceipt($id));
    }

    public function cancelReservation(int $id, RestaurantPortal $portal): RedirectResponse
    {
        $result = $portal->cancelReservation($id);

        return back()->with('success', $result['message'] ?? 'Reserva cancelada.');
    }

    public function rescheduleReservation(int $id, Request $request, RestaurantPortal $portal): RedirectResponse
    {
        $result = $portal->rescheduleReservation($id, $request->all());

        return back()->with('success', $result['message']);
    }

    public function preferences(Request $request, RestaurantPortal $portal): RedirectResponse
    {
        $result = $portal->savePreferences($request->all());

        return back()->with('success', $result['message']);
    }

    public function submitReview(Request $request, RestaurantPortal $portal): RedirectResponse
    {
        $result = $portal->submitReview($request->all(), $request->file('photo'));

        return back()->with('success', $result['message']);
    }

    public function createSupportTicket(Request $request, RestaurantPortal $portal): RedirectResponse
    {
        $result = $portal->createSupportTicket($request->all(), $request->file('attachment'));

        return back()->with('success', $result['message']);
    }

    public function replySupportTicket(int $id, Request $request, RestaurantPortal $portal): RedirectResponse
    {
        $result = $portal->replySupportTicket($id, $request->all(), $request->file('attachment'));

        return back()->with('success', $result['message']);
    }

    public function toggleFavorite(int $productId, RestaurantPortal $portal): RedirectResponse
    {
        $result = $portal->toggleFavorite($productId);

        return back()->with('success', $result['message'])->with('result', $result);
    }

    public function sendVerification(Request $request, RestaurantPortal $portal): RedirectResponse
    {
        $result = $portal->sendVerificationCode($request->all());

        return back()->with('success', $result['message'])->with('result', $result);
    }

    public function confirmVerification(Request $request, RestaurantPortal $portal): RedirectResponse
    {
        $result = $portal->confirmVerificationCode($request->all());

        return back()->with('success', $result['message']);
    }

    public function deactivate(Request $request, RestaurantPortal $portal): RedirectResponse
    {
        $result = $portal->deactivateAccount($request->all());

        return redirect('/')->with('success', $result['message']);
    }

    public function deleteAccount(Request $request, RestaurantPortal $portal): RedirectResponse
    {
        $result = $portal->deleteAccount($request->all());

        return redirect('/')->with('success', $result['message']);
    }
}
