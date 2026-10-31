<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Services\NiubizDiningPayment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiningPaymentController extends Controller
{
    public function start(Request $request, NiubizDiningPayment $payments): JsonResponse
    {
        return response()->json($payments->start((string) $request->header('X-Dining-Token'), $request->all(), $request->ip()));
    }

    public function checkout(string $attempt, NiubizDiningPayment $payments): View
    {
        return view('desego.dining-payment', $payments->checkout($attempt));
    }

    public function complete(Request $request, string $attempt, NiubizDiningPayment $payments): View
    {
        return view('desego.dining-payment-result', $payments->complete($attempt, (string) $request->input('transactionToken')));
    }
}
