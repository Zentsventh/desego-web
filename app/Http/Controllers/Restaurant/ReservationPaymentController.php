<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Services\NiubizReservationPayment;
use App\Services\ReservationAvailabilityService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReservationPaymentController extends Controller
{
    public function start(Request $request, NiubizReservationPayment $payments): JsonResponse
    {
        $data = $request->validate(['hold_token' => 'required|string|max:64']);

        return response()->json($payments->start($data['hold_token'], (string) $request->ip()));
    }

    public function checkout(string $attempt, NiubizReservationPayment $payments): View
    {
        return view('desego.reservation-payment', $payments->checkout($attempt));
    }

    public function complete(Request $request, string $attempt, NiubizReservationPayment $payments): View
    {
        $transactionToken = $request->input('transactionToken', $request->input('transactionCode'));
        if (! is_string($transactionToken) || $transactionToken === '') {
            return view('desego.reservation-payment-result', [
                'result' => [
                    'paid' => false,
                    'message' => 'Niubiz no devolvió un resultado de pago. La reserva no fue confirmada.',
                    'reservation' => null,
                ],
            ]);
        }

        $result = $payments->complete($attempt, $transactionToken);

        return view('desego.reservation-payment-result', ['result' => $result]);
    }

    public function confirmWithoutAdvance(
        Request $request,
        ReservationAvailabilityService $reservations
    ): JsonResponse {
        $data = $request->validate(['hold_token' => 'required|string|max:64']);
        $hold = DB::table('reservation_holds')->where('hold_token', $data['hold_token'])->first() ?? abort(404);
        abort_unless(
            (int) round((float) $hold->advance_amount * 100) === 0,
            422,
            'Esta reserva requiere el pago del anticipo mediante Niubiz.'
        );

        return response()->json($reservations->confirmPayment($data['hold_token'], 'direct'));
    }
}
