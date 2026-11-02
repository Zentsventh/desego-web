<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Throwable;

use function App\Support\Desego\desego_db;
use function App\Support\Desego\loyalty_award_order_points;

class NiubizOrderPayment
{
    public function available(): bool
    {
        return filled(config('services.niubiz.user')) && filled(config('services.niubiz.password'))
            && filled(config('services.niubiz.merchant_id'))
            && in_array(config('services.niubiz.env'), ['sandbox', 'production'], true);
    }

    private function baseUrl(): string
    {
        return config('services.niubiz.env') === 'production' ? 'https://apiprod.vnforapps.com' : 'https://apisandbox.vnforappstest.com';
    }

    private function accessToken(): string
    {
        $response = Http::connectTimeout(5)->timeout(20)
            ->withBasicAuth((string) config('services.niubiz.user'), (string) config('services.niubiz.password'))
            ->get($this->baseUrl().'/api.security/v1/security');
        abort_unless($response->successful() && strlen(trim($response->body())) > 20, 503, 'Niubiz no está disponible.');

        return trim($response->body());
    }

    public function hasPendingAttempt(int $orderId): bool
    {
        return DB::table('order_payment_attempts')->where('order_id', $orderId)->where(function ($query): void {
            $query->whereIn('status', ['authorizing', 'unknown', 'review'])->orWhere(function ($pending): void {
                $pending->whereIn('status', ['creating', 'ready'])->where('expires_at', '>', now());
            });
        })->exists();
    }

    public function start(int $orderId, ?int $customerId, string $ip, ?string $guestCheckoutKey = null): string
    {
        abort_unless($this->available(), 503, 'Los pagos con Niubiz no están habilitados.');
        $paymentStart = DB::transaction(function () use ($orderId, $customerId, $guestCheckoutKey): array {
            $orderQuery = DB::table('orders')
                ->leftJoin('customers', 'customers.id', '=', 'orders.customer_id')
                ->where('orders.id', $orderId)
                ->select('orders.*', 'customers.email as customer_email', 'customers.user_id as customer_user_id');
            if ($customerId !== null) {
                $orderQuery->where('orders.customer_id', $customerId);
            } else {
                $orderQuery->whereNull('customers.user_id')
                    ->where('orders.payment_preference', 'Niubiz')
                    ->where('orders.web_checkout_key', $guestCheckoutKey);
            }
            $order = $orderQuery->lockForUpdate()->first() ?? abort(404);
            abort_if($order->dining_session_id !== null, 409, 'Paga los consumos de mesa desde el QR de tu mesa para evitar pagos duplicados.');
            abort_unless($order->payment_status === 'Pendiente' && $order->status !== 'Cancelado' && (float) $order->total > 0, 409, 'Este pedido no tiene un pago pendiente.');
            $ready = DB::table('order_payment_attempts')->where('order_id', $orderId)->where('status', 'ready')->where('expires_at', '>', now())->first();
            if ($ready) {
                return ['attempt' => $ready, 'customer_email' => $order->customer_email];
            }
            abort_if($this->hasPendingAttempt($orderId), 409, 'Ya hay un pago en curso. Espera su resultado antes de intentarlo de nuevo.');
            DB::table('cash_register_mutex')->where('id', 1)->lockForUpdate()->first();
            $cash = DB::table('cash_sessions')->where('status', 'open')->lockForUpdate()->first();
            abort_unless($cash, 409, 'La caja está cerrada. Contacta al restaurante para realizar el pago.');
            $id = (string) Str::uuid();
            DB::table('order_payment_attempts')->insert([
                'id' => $id, 'order_id' => $orderId, 'purchase_number' => (string) random_int(100000000000, 999999999999),
                'merchant_id' => config('services.niubiz.merchant_id'), 'environment' => config('services.niubiz.env'),
                'status' => 'creating', 'amount_cents' => (int) round((float) $order->total * 100),
                'cash_session_id' => $cash->id, 'actor_id' => $cash->opened_by,
                'expires_at' => now()->addMinutes(10), 'created_at' => now(), 'updated_at' => now(),
            ]);

            return [
                'attempt' => DB::table('order_payment_attempts')->where('id', $id)->first(),
                'customer_email' => $order->customer_email,
            ];
        });
        $attempt = $paymentStart['attempt'];
        $customerEmail = $paymentStart['customer_email'];

        if ($attempt->status === 'ready') {
            return URL::temporarySignedRoute('customer.payment.checkout', Carbon::parse($attempt->expires_at), ['attempt' => $attempt->id]);
        }

        try {
            $response = Http::connectTimeout(5)->timeout(25)->withHeaders(['Authorization' => $this->accessToken()])
                ->post($this->baseUrl().'/api.ecommerce/v2/ecommerce/token/session/'.$attempt->merchant_id, [
                    'channel' => 'web', 'amount' => $attempt->amount_cents / 100,
                    'antifraud' => ['clientIp' => $ip, 'merchantDefineData' => array_filter([
                        'MDD4' => $customerEmail,
                        'MDD32' => $customerId !== null ? (string) $customerId : null,
                        'MDD75' => $customerId !== null ? 'Registrado' : 'Invitado',
                        'MDD77' => $customerId !== null ? 1 : 0,
                    ], static fn (mixed $value): bool => $value !== null && $value !== '')],
                ]);
            $sessionKey = $response->json('sessionKey');
            abort_unless($response->successful() && is_string($sessionKey) && $sessionKey !== '', 503);
        } catch (Throwable $exception) {
            Log::warning('No se pudo iniciar el formulario de pago Niubiz.', [
                'attempt_id' => $attempt->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
            DB::table('order_payment_attempts')->where('id', $attempt->id)->update(['status' => 'failed', 'updated_at' => now()]);
            abort(503, 'No pudimos iniciar Niubiz. Tu pedido sigue pendiente y no se realizó ningún cobro.');
        }
        DB::table('order_payment_attempts')->where('id', $attempt->id)->update([
            'status' => 'ready', 'session_key' => Crypt::encryptString($sessionKey), 'updated_at' => now(),
        ]);

        return URL::temporarySignedRoute('customer.payment.checkout', Carbon::parse($attempt->expires_at), ['attempt' => $attempt->id]);
    }

    /** @return array{attempt: object, sessionKey: string, merchant: string, sandbox: bool, script: string, callback: string} */
    public function checkout(string $id): array
    {
        $attempt = DB::table('order_payment_attempts')->where('id', $id)->first() ?? abort(404);
        abort_unless($attempt->status === 'ready' && now()->lessThan($attempt->expires_at), 409, 'El formulario de pago venció o ya fue utilizado. Regresa a tu cuenta.');
        $sandbox = $attempt->environment === 'sandbox';

        return [
            'attempt' => $attempt, 'sessionKey' => Crypt::decryptString($attempt->session_key), 'merchant' => $attempt->merchant_id, 'sandbox' => $sandbox,
            'script' => $sandbox ? 'https://static-content-qas.vnforapps.com/v2/js/checkout.js?qa=true' : 'https://static-content.vnforapps.com/v2/js/checkout.js',
            'callback' => URL::temporarySignedRoute('customer.payment.return', Carbon::parse($attempt->expires_at), ['attempt' => $id]),
        ];
    }

    /** @return array{paid: bool, message: string} */
    public function complete(string $id, string $transactionToken): array
    {
        abort_unless(preg_match('/^[a-zA-Z0-9_-]{20,200}$/', $transactionToken), 422, 'El token de pago no es válido.');
        $attempt = DB::transaction(function () use ($id, $transactionToken): object {
            $attempt = DB::table('order_payment_attempts')->where('id', $id)->lockForUpdate()->first() ?? abort(404);
            if (in_array($attempt->status, ['paid', 'review', 'unknown', 'declined'], true)) {
                return $attempt;
            }
            abort_unless($attempt->status === 'ready' && now()->lessThan($attempt->expires_at), 409, 'Este pago ya está en proceso o venció. Consulta el estado en tu cuenta.');
            abort_unless($attempt->merchant_id === (string) config('services.niubiz.merchant_id') && $attempt->environment === config('services.niubiz.env'), 409);
            abort_if(DB::table('order_payment_attempts')->where('transaction_hash', hash('sha256', $transactionToken))->exists(), 409, 'Este token ya fue utilizado.');
            DB::table('order_payment_attempts')->where('id', $id)->update(['status' => 'authorizing', 'transaction_hash' => hash('sha256', $transactionToken), 'updated_at' => now()]);

            return $attempt;
        });
        if ($attempt->status === 'paid') {
            return ['paid' => true, 'message' => 'Tu pago ya fue confirmado.'];
        }
        if (in_array($attempt->status, ['review', 'unknown', 'declined'], true)) {
            return $this->reconcile($id);
        }

        try {
            $response = Http::connectTimeout(5)->timeout(40)->withHeaders(['Authorization' => $this->accessToken()])
                ->post($this->baseUrl().'/api.authorization/v3/authorization/ecommerce/'.$attempt->merchant_id, [
                    'channel' => 'web', 'captureType' => 'manual', 'countable' => true,
                    'order' => ['tokenId' => $transactionToken, 'purchaseNumber' => $attempt->purchase_number, 'amount' => $attempt->amount_cents / 100, 'currency' => 'PEN'],
                ]);
        } catch (Throwable) {
            DB::table('order_payment_attempts')->where('id', $id)->update(['status' => 'unknown', 'updated_at' => now()]);

            return $this->reconcile($id);
        }

        $result = $this->processProviderResponse($attempt, $response);
        if ($result !== null) {
            return $result;
        }

        return $this->reconcile($id);
    }

    /** @return array{paid: bool, message: string} */
    public function reconcile(string $id): array
    {
        $attempt = DB::table('order_payment_attempts')->where('id', $id)->first() ?? abort(404);
        if ($attempt->status === 'paid') {
            return ['paid' => true, 'message' => 'Pago confirmado por Niubiz. Tu pedido ya está pagado.'];
        }
        if ($attempt->status === 'declined') {
            return ['paid' => false, 'message' => 'Niubiz no autorizó el pago. Tu pedido sigue pendiente; puedes intentarlo con otro medio.'];
        }
        if ($attempt->status === 'review') {
            return ['paid' => false, 'message' => 'El pedido fue cancelado. El restaurante verificará la transacción.'];
        }
        abort_unless($attempt->status === 'unknown', 409, 'Este pago todavía está en proceso.');
        abort_unless($attempt->merchant_id === (string) config('services.niubiz.merchant_id') && $attempt->environment === config('services.niubiz.env'), 409);

        try {
            $response = Http::connectTimeout(5)->timeout(25)->withHeaders(['Authorization' => $this->accessToken()])
                ->get($this->baseUrl().'/api.authorization/v3/retrieve/purchase/'.$attempt->merchant_id.'/'.$attempt->purchase_number);
            $result = $this->processProviderResponse($attempt, $response, true);
            if ($result !== null) {
                return $result;
            }
        } catch (Throwable $exception) {
            Log::warning('No se pudo consultar el estado del pago en Niubiz.', ['attempt_id' => $id, 'exception' => $exception::class]);
        }

        return ['paid' => false, 'message' => 'Esperando confirmación de Niubiz. No vuelvas a pagar mientras se confirma el resultado. Puedes consultar el estado sin realizar otro cobro.'];
    }

    /** @return array{paid: bool, message: string}|null */
    private function processProviderResponse(object $attempt, Response $response, bool $retrieved = false): ?array
    {
        $code = $response->json('dataMap.ACTION_CODE') ?: ($response->json('data.ACTION_CODE') ?: $response->json('order.actionCode'));
        $matches = $response->successful() && $response->json('order.currency') === 'PEN'
            && (string) $response->json('order.purchaseNumber') === $attempt->purchase_number
            && (int) round((float) $response->json('order.authorizedAmount', -1) * 100) === (int) $attempt->amount_cents
            && (string) $response->json('fulfillment.merchantId') === $attempt->merchant_id;
        $providerStatus = $response->json('dataMap.STATUS') ?? $response->json('order.status');
        $approved = $matches && $code === '000'
            && (! $retrieved || in_array($providerStatus, ['Authorized', 'Confirmed'], true))
            && filled($response->json('order.transactionId'));
        if ($approved) {
            return $this->recordApprovedPayment($attempt, (string) $response->json('order.transactionId'));
        }

        $declined = $retrieved
            ? $matches && $providerStatus === 'Not Authorized' && $code !== '000'
            : ! $response->serverError() && filled($code) && $code !== '000';
        DB::table('order_payment_attempts')->where('id', $attempt->id)->whereIn('status', ['authorizing', 'unknown'])->update([
            'status' => $declined ? 'declined' : 'unknown', 'updated_at' => now(),
        ]);
        Log::info('Resultado de la verificación automática con Niubiz.', [
            'attempt_id' => $attempt->id, 'http_status' => $response->status(),
            'action_code' => $code, 'provider_status' => $providerStatus, 'matches' => $matches,
        ]);

        return $declined ? ['paid' => false, 'message' => 'Niubiz no autorizó el pago. El banco rechazó la operación o no completó la autorización. Tu pedido sigue pendiente; puedes intentarlo con otro medio.'] : null;
    }

    /** @return array{paid: bool, message: string} */
    private function recordApprovedPayment(object $attempt, string $reference): array
    {
        try {
            return DB::transaction(function () use ($attempt, $reference): array {
                $order = DB::table('orders')->where('id', $attempt->order_id)->lockForUpdate()->first();
                DB::table('order_payment_attempts')->where('id', $attempt->id)->lockForUpdate()->first();

                if (! $order || $order->status === 'Cancelado' || (int) round((float) $order->total * 100) !== (int) $attempt->amount_cents) {
                    DB::table('order_payment_attempts')->where('id', $attempt->id)->update([
                        'status' => 'review',
                        'provider_reference' => $reference,
                        'session_key' => null,
                        'updated_at' => now(),
                    ]);

                    return ['paid' => false, 'message' => 'El pedido fue cancelado o su importe cambió. El restaurante verificará la transacción; no vuelvas a pagar.'];
                }

                if ($order->payment_status === 'Pagado') {
                    DB::table('order_payment_attempts')->where('id', $attempt->id)->update([
                        'status' => 'paid',
                        'provider_reference' => $reference,
                        'session_key' => null,
                        'updated_at' => now(),
                    ]);

                    return ['paid' => true, 'message' => 'Pago confirmado por Niubiz. Tu pedido ya está pagado.'];
                }

                $cashSessionId = $attempt->cash_session_id;
                $actorId = (int) ($attempt->actor_id ?: 1);

                DB::table('payments')->insert([
                    'order_id' => $order->id,
                    'cash_session_id' => $cashSessionId,
                    'user_id' => $actorId,
                    'payment_type' => 'payment',
                    'method' => 'Tarjeta',
                    'amount' => $order->total,
                    'notes' => 'Niubiz '.$attempt->purchase_number.' · '.$reference,
                    'created_at' => now(),
                ]);

                DB::table('orders')->where('id', $order->id)->update([
                    'payment_status' => 'Pagado',
                    'payment_method' => 'Tarjeta',
                    'payment_preference' => 'Niubiz',
                    'cash_session_id' => $cashSessionId,
                    'paid_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('order_payment_attempts')->where('id', $attempt->id)->update([
                    'status' => 'paid',
                    'provider_reference' => $reference,
                    'session_key' => null,
                    'updated_at' => now(),
                ]);

                loyalty_award_order_points(desego_db(), $order->id, (int) $actorId);

                return ['paid' => true, 'message' => 'Pago confirmado por Niubiz. Tu pedido ya está pagado.'];
            });
        } catch (Throwable $exception) {
            DB::table('order_payment_attempts')->where('id', $attempt->id)->where('status', 'authorizing')->update([
                'status' => 'unknown', 'provider_reference' => $reference, 'updated_at' => now(),
            ]);
            Log::error('Pago Niubiz aprobado pendiente de conciliación.', ['attempt_id' => $attempt->id, 'exception' => $exception::class]);

            return ['paid' => false, 'message' => 'Niubiz aprobó el pago. El restaurante debe confirmar su registro; no vuelvas a pagar.'];
        }
    }
}
