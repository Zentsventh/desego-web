<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

use function App\Support\Desego\audit_log;
use function App\Support\Desego\desego_db;
use function App\Support\Desego\loyalty_award_order_points;

class NiubizDiningPayment
{
    public function __construct(private DiningService $dining) {}

    public function baseUrl(): string
    {
        return config('dining.niubiz.environment') === 'production' ? 'https://apiprod.vnforapps.com' : 'https://apisandbox.vnforappstest.com';
    }

    private function accessToken(): string
    {
        $response = Http::connectTimeout(10)->timeout(30)->withBasicAuth((string) config('dining.niubiz.user'), (string) config('dining.niubiz.password'))->get($this->baseUrl().'/api.security/v1/security');
        abort_unless($response->successful() && strlen(trim($response->body())) > 20, 503, 'Niubiz no pudo iniciar la conexión de pago. Inténtalo más tarde.');

        return trim($response->body());
    }

    public function start(string $token, array $input, string $ip): array
    {
        abort_unless(filled(config('dining.niubiz.merchant_id')) && filled(config('dining.niubiz.password')), 503, 'Los pagos en línea aún no están habilitados.');
        $data = Validator::make($input, ['scope' => 'required|in:mine,all', 'email' => 'required|email|max:80', 'phone' => 'required|regex:/^[0-9+ ]{7,15}$/', 'address' => 'required|string|max:60', 'city' => 'required|string|max:50', 'postal_code' => 'required|digits_between:5,9'])->validate();
        $member = $this->dining->member($token);
        $attempt = DB::transaction(function () use ($member, $data): object {
            $visit = DB::table('dining_sessions')->where('id', $member->dining_session_id)->lockForUpdate()->first();
            abort_unless(in_array($visit->status, ['open', 'checkout'], true), 409, 'La visita ya está pagada o cerrada.');
            $active = DB::table('dining_payment_attempts')->where('dining_session_id', $visit->id)->where(function ($query): void {
                $query->whereIn('status', ['authorizing', 'unknown'])->orWhere(function ($pending): void {
                    $pending->whereIn('status', ['creating', 'ready'])->where('expires_at', '>', now());
                });
            })->exists();
            abort_if($active, 409, 'Ya hay un pago en curso en esta mesa. Espera su resultado antes de volver a pagar.');
            DB::table('cash_register_mutex')->where('id', 1)->lockForUpdate()->first();
            $cash = DB::table('cash_sessions')->where('status', 'open')->lockForUpdate()->first();
            if (! $cash) {
                $actorId = DB::table('users')->join('roles', 'roles.id', '=', 'users.role_id')->where('roles.code', 'admin')->where('users.status', 'active')->value('users.id')
                    ?? DB::table('users')->where('status', 'active')->value('id');
                abort_unless($actorId, 409, 'La caja del restaurante está cerrada. Pide ayuda al mesero.');
                $cashId = DB::table('cash_sessions')->insertGetId([
                    'opened_by' => $actorId,
                    'opening_amount' => 0,
                    'status' => 'open',
                    'opening_notes' => 'Apertura automática desde pago Niubiz de mesa QR',
                    'opened_at' => now(),
                ]);
                $cash = DB::table('cash_sessions')->where('id', $cashId)->first();
            }
            $orders = DB::table('orders')->where('dining_session_id', $visit->id)->where('status', '<>', 'Cancelado')->where('payment_status', 'Pendiente')->when($data['scope'] === 'mine', fn ($query) => $query->where('dining_member_id', $member->id))->lockForUpdate()->get();
            abort_if($orders->isEmpty(), 409, 'No tienes consumo pendiente en esta selección.');
            $id = (string) Str::uuid();
            DB::table('dining_payment_attempts')->insert(['id' => $id, 'dining_session_id' => $visit->id, 'dining_member_id' => $member->id, 'purchase_number' => (string) random_int(100000000000, 999999999999), 'status' => 'creating', 'amount_cents' => (int) round($orders->sum('total') * 100), 'cash_session_id' => $cash->id, 'actor_id' => $cash->opened_by, 'expires_at' => now()->addMinutes(10), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('dining_payment_attempt_orders')->insert($orders->map(fn (object $order): array => ['attempt_id' => $id, 'order_id' => $order->id])->all());
            DB::table('dining_sessions')->where('id', $visit->id)->update(['status' => 'checkout', 'updated_at' => now()]);

            return DB::table('dining_payment_attempts')->where('id', $id)->first();
        });
        $isSandbox = config('dining.niubiz.environment') !== 'production';
        try {
            $access = $this->accessToken();
            $response = Http::connectTimeout(10)->timeout(30)->withHeaders(['Authorization' => $access])->post($this->baseUrl().'/api.ecommerce/v2/ecommerce/token/session/'.config('dining.niubiz.merchant_id'), ['channel' => 'web', 'amount' => $attempt->amount_cents / 100, 'antifraud' => ['clientIp' => $ip, 'merchantDefineData' => ['MDD4' => $data['email'], 'MDD32' => $member->id, 'MDD75' => 'Invitado', 'MDD77' => 0]], 'dataMap' => ['cardholderCity' => $data['city'], 'cardholderCountry' => 'PE', 'cardholderAddress' => $data['address'], 'cardholderPostalCode' => $data['postal_code'], 'cardholderState' => 'LIM', 'cardholderPhoneNumber' => preg_replace('/\D/', '', $data['phone'])]]);
            abort_unless($response->successful() && is_string($response->json('sessionKey')), 503, 'Niubiz no pudo abrir el formulario de pago. No se realizó ningún cobro.');
            $sessionKey = $response->json('sessionKey');
        } catch (Throwable $exception) {
            if (! $isSandbox) {
                DB::table('dining_payment_attempts')->where('id', $attempt->id)->update(['status' => 'failed', 'updated_at' => now()]);
                abort(503, 'No pudimos iniciar el pago. No se realizó ningún cobro; intenta nuevamente.');
            }
            $sessionKey = 'sandbox-session-'.Str::random(24);
        }
        $checkoutUrl = URL::temporarySignedRoute('dining.payment.checkout', now()->addMinutes(10), ['attempt' => $attempt->id]);
        DB::table('dining_payment_attempts')->where('id', $attempt->id)->update(['status' => 'ready', 'session_key' => Crypt::encryptString($sessionKey), 'checkout_url' => $checkoutUrl, 'updated_at' => now()]);

        return ['ok' => true, 'checkout_url' => $checkoutUrl, 'attempt_id' => $attempt->id, 'sandbox' => $isSandbox];
    }

    public function checkout(string $id): array
    {
        $attempt = DB::table('dining_payment_attempts')->where('id', $id)->first() ?? abort(404);
        abort_unless($attempt->status === 'ready' && now()->lessThan($attempt->expires_at), 409, 'Este formulario ya se utilizó o venció. Regresa a tu mesa.');

        return ['attempt' => $attempt, 'sessionKey' => Crypt::decryptString($attempt->session_key), 'merchant' => config('dining.niubiz.merchant_id'), 'sandbox' => config('dining.niubiz.environment') !== 'production', 'script' => config('dining.niubiz.environment') === 'production' ? 'https://static-content.vnforapps.com/v2/js/checkout.js' : 'https://static-content-qas.vnforapps.com/env/sandbox/js/checkout.js', 'callback' => URL::temporarySignedRoute('dining.payment.return', now()->addMinutes(12), ['attempt' => $id])];
    }

    public function complete(string $id, string $transactionToken): array
    {
        abort_unless(preg_match('/^[a-zA-Z0-9-]{20,100}$/', $transactionToken), 422, 'El token de transacción no es válido.');
        $attempt = DB::transaction(function () use ($id, $transactionToken): object {
            $attempt = DB::table('dining_payment_attempts')->where('id', $id)->lockForUpdate()->first() ?? abort(404);
            if ($attempt->status === 'paid') {
                return $attempt;
            }
            abort_unless($attempt->status === 'ready' && now()->lessThan($attempt->expires_at), 409, 'Este pago ya se está verificando o venció. Consulta el estado de tu cuenta.');
            DB::table('dining_payment_attempts')->where('id', $id)->update(['status' => 'authorizing', 'transaction_hash' => hash('sha256', $transactionToken), 'updated_at' => now()]);

            return $attempt;
        });
        if ($attempt->status === 'paid') {
            return ['paid' => true, 'message' => 'Tu pago ya fue confirmado.'];
        }
        $isSandboxDemo = config('dining.niubiz.environment') !== 'production' && str_starts_with($transactionToken, 'SANDBOX-DEMO-');
        if ($isSandboxDemo) {
            $order = [
                'actionCode' => '000',
                'currency' => 'PEN',
                'purchaseNumber' => $attempt->purchase_number,
                'authorizedAmount' => $attempt->amount_cents / 100,
                'transactionId' => 'SBX-'.$attempt->purchase_number,
            ];
            $approved = true;
        } else {
            try {
                $response = Http::connectTimeout(10)->timeout(55)->withHeaders(['Authorization' => $this->accessToken()])->post($this->baseUrl().'/api.authorization/v3/authorization/ecommerce/'.config('dining.niubiz.merchant_id'), ['channel' => 'web', 'captureType' => 'manual', 'countable' => true, 'order' => ['tokenId' => $transactionToken, 'purchaseNumber' => $attempt->purchase_number, 'amount' => $attempt->amount_cents / 100, 'currency' => 'PEN']]);
            } catch (Throwable $exception) {
                DB::table('dining_payment_attempts')->where('id', $id)->update(['status' => 'unknown', 'updated_at' => now()]);

                return ['paid' => false, 'message' => 'El banco aún no confirmó el resultado. No repitas el pago: el restaurante debe verificarlo con Niubiz.'];
            }
            $order = $response->json('order');
            $approved = $response->successful() && is_array($order) && ($order['actionCode'] ?? null) === '000' && ($order['currency'] ?? null) === 'PEN' && (string) ($order['purchaseNumber'] ?? '') === $attempt->purchase_number && (int) round((float) ($order['authorizedAmount'] ?? -1) * 100) === $attempt->amount_cents && (string) $response->json('fulfillment.merchantId') === (string) config('dining.niubiz.merchant_id');
            if (! $approved) {
                $declined = ! $response->serverError() && filled($response->json('data.ACTION_CODE')) && $response->json('data.ACTION_CODE') !== '000';
                DB::table('dining_payment_attempts')->where('id', $id)->update(['status' => $declined ? 'declined' : 'unknown', 'updated_at' => now()]);

                return ['paid' => false, 'message' => $declined ? 'El banco rechazó el pago. Puedes intentar con otra tarjeta.' : 'No se pudo verificar la respuesta de Niubiz. Por favor intenta nuevamente o consulta al restaurante.'];
            }
        }

        return DB::transaction(function () use ($attempt, $order): array {
            $visit = DB::table('dining_sessions')->where('id', $attempt->dining_session_id)->lockForUpdate()->first();
            DB::table('dining_payment_attempts')->where('id', $attempt->id)->lockForUpdate()->first();
            $orderIds = DB::table('dining_payment_attempt_orders')->where('attempt_id', $attempt->id)->pluck('order_id');
            $orders = DB::table('orders')->whereIn('id', $orderIds)->where('dining_session_id', $attempt->dining_session_id)->lockForUpdate()->get();
            $pendingOrders = $orders->filter(fn (object $item): bool => $item->payment_status === 'Pendiente' && $item->status !== 'Cancelado');
            foreach ($pendingOrders as $item) {
                DB::table('payments')->insert(['order_id' => $item->id, 'cash_session_id' => $attempt->cash_session_id, 'user_id' => $attempt->actor_id, 'payment_type' => 'payment', 'method' => 'Tarjeta', 'amount' => $item->total, 'notes' => 'Niubiz '.$attempt->purchase_number, 'created_at' => now()]);
                DB::table('orders')->where('id', $item->id)->update(['payment_status' => 'Pagado', 'payment_method' => 'Tarjeta', 'cash_session_id' => $attempt->cash_session_id, 'paid_at' => now(), 'updated_at' => now()]);
                loyalty_award_order_points(desego_db(), $item->id, $attempt->actor_id);
            }
            DB::table('dining_payment_attempts')->where('id', $attempt->id)->update(['status' => 'paid', 'session_key' => null, 'provider_reference' => $order['transactionId'] ?? $order['authorizationCode'] ?? null, 'updated_at' => now()]);
            DB::table('dining_requests')->where('dining_session_id', $visit->id)->where('kind', 'bill')->whereIn('status', ['pending', 'acknowledged'])->update(['status' => 'resolved', 'handled_by' => $attempt->actor_id, 'handled_at' => now(), 'updated_at' => now()]);
            $remaining = DB::table('orders')->where('dining_session_id', $visit->id)->where('status', '<>', 'Cancelado')->where('payment_status', 'Pendiente')->exists();
            DB::table('dining_sessions')->where('id', $visit->id)->update(['status' => $remaining ? 'checkout' : 'paid', 'updated_at' => now()]);
            audit_log(desego_db(), null, 'dining.niubiz_paid', ['attempt_id' => $attempt->id, 'session_id' => $visit->id]);

            return ['paid' => true, 'message' => 'Pago confirmado. ¡Gracias por compartir tu mesa con Desego!'];
        });
    }
}
