<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Services\NiubizOrderPayment;
use App\Services\RestaurantPortal;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class OrderPaymentController extends Controller
{
    public function start(Request $request, int $id, RestaurantPortal $portal, NiubizOrderPayment $payments): Response
    {
        $customer = $portal->customer();
        $url = $payments->start($id, (int) $customer->id, (string) $request->ip());

        if ($request->expectsJson()) {
            return response()->json(['checkout_url' => $url]);
        }

        return $request->header('X-Inertia') ? Inertia::location($url) : redirect()->to($url);
    }

    public function checkout(string $attempt, NiubizOrderPayment $payments): View
    {
        return view('desego.order-payment', $payments->checkout($attempt));
    }

    public function complete(Request $request, string $attempt, NiubizOrderPayment $payments): View
    {
        $data = $request->validate(['transactionToken' => 'required|string|max:200']);

        $result = $payments->complete($attempt, $data['transactionToken']);

        return $this->result($attempt, $result);
    }

    public function status(string $attempt, NiubizOrderPayment $payments): View
    {
        return $this->result($attempt, $payments->reconcile($attempt));
    }

    /** @param array{paid: bool, message: string} $result */
    private function result(string $attempt, array $result): View
    {
        $receipt = DB::table('order_payment_attempts')->where('id', $attempt)->first(['purchase_number', 'amount_cents', 'provider_reference', 'updated_at', 'status']);
        $statusUrl = $receipt->status === 'unknown'
            ? URL::temporarySignedRoute('customer.payment.status', now()->addDay(), ['attempt' => $attempt])
            : null;

        return view('desego.order-payment-result', [...$result, 'receipt' => $receipt, 'statusUrl' => $statusUrl]);
    }
}
