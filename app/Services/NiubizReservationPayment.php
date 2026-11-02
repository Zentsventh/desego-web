<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Throwable;

class NiubizReservationPayment
{
    public function __construct(private ReservationAvailabilityService $reservations) {}

    public function available(): bool
    {
        return filled(config('dining.niubiz.user'))
            && filled(config('dining.niubiz.password'))
            && filled(config('dining.niubiz.merchant_id'))
            && in_array(config('dining.niubiz.environment'), ['sandbox', 'production'], true);
    }

    private function baseUrl(): string
    {
        return config('dining.niubiz.environment') === 'production'
            ? 'https://apiprod.vnforapps.com'
            : 'https://apisandbox.vnforappstest.com';
    }

    private function accessToken(): string
    {
        $response = Http::connectTimeout(5)
            ->timeout(20)
            ->withBasicAuth(
                (string) config('dining.niubiz.user'),
                (string) config('dining.niubiz.password')
            )
            ->get($this->baseUrl().'/api.security/v1/security');

        if (! $response->successful() || strlen(trim($response->body())) < 20) {
            throw new \RuntimeException('Niubiz no emitió un token de acceso válido.');
        }

        return trim($response->body());
    }

    /**
     * @return array{ok: true, checkout_url: string, attempt_id: string, sandbox: bool}
     */
    public function start(string $holdToken, string $ip): array
    {
        abort_unless($this->available(), 503, 'El pago con Niubiz no está configurado.');

        $attempt = DB::transaction(function () use ($holdToken): object {
            $hold = DB::table('reservation_holds')
                ->where('hold_token', $holdToken)
                ->lockForUpdate()
                ->first() ?? abort(404, 'No encontramos el bloqueo de la reserva.');

            abort_unless(
                $hold->status === 'ACTIVE' && CarbonImmutable::parse($hold->expires_at)->isFuture(),
                409,
                'El tiempo para completar la reserva venció. Selecciona nuevamente tu horario.'
            );
            abort_if((int) round((float) $hold->advance_amount * 100) < 1, 422, 'Esta reserva no requiere un pago en línea.');

            $readyAttempt = DB::table('reservation_payment_attempts')
                ->where('hold_id', $hold->id)
                ->where('status', 'ready')
                ->where('expires_at', '>', now())
                ->first();

            if ($readyAttempt) {
                return $readyAttempt;
            }

            $activeAttempt = DB::table('reservation_payment_attempts')
                ->where('hold_id', $hold->id)
                ->where(function ($query): void {
                    $query->whereIn('status', ['authorizing', 'unknown', 'review'])
                        ->orWhere(function ($pending): void {
                            $pending->whereIn('status', ['creating', 'ready'])
                                ->where('expires_at', '>', now());
                        });
                })
                ->exists();
            abort_if($activeAttempt, 409, 'El resultado de un pago anterior aún está pendiente de verificación. No vuelvas a pagar.');

            $id = (string) Str::uuid();
            $attemptExpiry = CarbonImmutable::now('UTC')->addMinutes(10);
            $holdExpiry = CarbonImmutable::parse($hold->expires_at, 'UTC');
            $expiresAt = $holdExpiry->lessThan($attemptExpiry) ? $holdExpiry : $attemptExpiry;

            DB::table('reservation_payment_attempts')->insert([
                'id' => $id,
                'hold_id' => $hold->id,
                'purchase_number' => (string) random_int(100000000000, 999999999999),
                'merchant_id' => (string) config('dining.niubiz.merchant_id'),
                'environment' => (string) config('dining.niubiz.environment'),
                'status' => 'creating',
                'amount_cents' => (int) round((float) $hold->advance_amount * 100),
                'expires_at' => $expiresAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return DB::table('reservation_payment_attempts')->where('id', $id)->first();
        });

        if ($attempt->status === 'ready') {
            return $this->checkoutResponse($attempt);
        }

        $hold = DB::table('reservation_holds')->where('id', $attempt->hold_id)->first();

        try {
            $response = Http::connectTimeout(5)
                ->timeout(25)
                ->withHeaders(['Authorization' => $this->accessToken()])
                ->post($this->baseUrl().'/api.ecommerce/v2/ecommerce/token/session/'.$attempt->merchant_id, [
                    'channel' => 'web',
                    'amount' => $attempt->amount_cents / 100,
                    'antifraud' => [
                        'clientIp' => $ip,
                        'merchantDefineData' => array_filter([
                            'MDD4' => $hold->customer_email,
                            'MDD75' => 'Invitado',
                            'MDD77' => 0,
                        ], static fn (mixed $value): bool => $value !== null && $value !== ''),
                    ],
                    'dataMap' => [
                        'cardholderCity' => 'Ate',
                        'cardholderCountry' => 'PE',
                        'cardholderAddress' => 'Huaycan',
                        'cardholderPostalCode' => '15479',
                        'cardholderState' => 'Lima',
                        'cardholderPhoneNumber' => preg_replace('/\D+/', '', (string) $hold->customer_phone),
                    ],
                ]);
        } catch (Throwable $exception) {
            $this->markAttempt($attempt->id, 'failed');
            Log::warning('No se pudo iniciar el checkout de Niubiz para una reserva.', [
                'attempt_id' => $attempt->id,
                'exception' => $exception::class,
            ]);

            abort(503, 'Niubiz no está disponible. La reserva no fue confirmada y no se realizó ningún cobro.');
        }

        $sessionKey = $response->json('sessionKey');
        if (! $response->successful() || ! is_string($sessionKey) || $sessionKey === '') {
            $this->markAttempt($attempt->id, 'failed');
            Log::warning('Niubiz rechazó la creación de sesión para una reserva.', [
                'attempt_id' => $attempt->id,
                'http_status' => $response->status(),
            ]);

            abort(503, 'Niubiz no pudo abrir el formulario. La reserva no fue confirmada y no se realizó ningún cobro.');
        }

        DB::table('reservation_payment_attempts')->where('id', $attempt->id)->update([
            'status' => 'ready',
            'session_key' => Crypt::encryptString($sessionKey),
            'updated_at' => now(),
        ]);
        $attempt = DB::table('reservation_payment_attempts')->where('id', $attempt->id)->first();

        return $this->checkoutResponse($attempt);
    }

    /**
     * @return array{ok: true, checkout_url: string, attempt_id: string, sandbox: bool}
     */
    private function checkoutResponse(object $attempt): array
    {
        return [
            'ok' => true,
            'checkout_url' => URL::temporarySignedRoute(
                'reservation.payment.checkout',
                CarbonImmutable::parse($attempt->expires_at),
                ['attempt' => $attempt->id],
                false
            ),
            'attempt_id' => $attempt->id,
            'sandbox' => $attempt->environment === 'sandbox',
        ];
    }

    /**
     * @return array{attempt: object, sessionKey: string, merchant: string, sandbox: bool, script: string, callback: string}
     */
    public function checkout(string $id): array
    {
        $attempt = DB::table('reservation_payment_attempts')->where('id', $id)->first() ?? abort(404);
        abort_unless(
            $attempt->status === 'ready' && now()->lessThan($attempt->expires_at),
            409,
            'El formulario de pago venció o ya fue utilizado. Vuelve a seleccionar tu horario.'
        );

        $sandbox = $attempt->environment === 'sandbox';

        return [
            'attempt' => $attempt,
            'sessionKey' => Crypt::decryptString($attempt->session_key),
            'merchant' => $attempt->merchant_id,
            'sandbox' => $sandbox,
            'script' => $sandbox
                ? 'https://static-content-qas.vnforapps.com/env/sandbox/js/checkout.js'
                : 'https://static-content.vnforapps.com/v2/js/checkout.js',
            'callback' => URL::temporarySignedRoute(
                'reservation.payment.return',
                CarbonImmutable::parse($attempt->expires_at)->addMinutes(2),
                ['attempt' => $id],
                false
            ),
        ];
    }

    /**
     * @return array{paid: bool, message: string, reservation?: array}
     */
    public function complete(string $id, string $transactionToken): array
    {
        abort_unless(
            preg_match('/^[a-zA-Z0-9_-]{20,200}$/', $transactionToken),
            422,
            'Niubiz no devolvió un token de pago válido.'
        );

        $attempt = DB::transaction(function () use ($id, $transactionToken): object {
            $attempt = DB::table('reservation_payment_attempts')
                ->where('id', $id)
                ->lockForUpdate()
                ->first() ?? abort(404);

            if ($attempt->status === 'paid') {
                return $attempt;
            }

            abort_unless(
                $attempt->status === 'ready' && now()->lessThan($attempt->expires_at),
                409,
                'Este intento de pago venció o ya fue procesado.'
            );
            abort_unless(
                $attempt->merchant_id === (string) config('dining.niubiz.merchant_id')
                    && $attempt->environment === (string) config('dining.niubiz.environment'),
                409,
                'La configuración de Niubiz cambió. Inicia un nuevo pago.'
            );

            $transactionHash = hash('sha256', $transactionToken);
            abort_if(
                DB::table('reservation_payment_attempts')->where('transaction_hash', $transactionHash)->exists(),
                409,
                'El token de Niubiz ya fue utilizado.'
            );

            DB::table('reservation_payment_attempts')->where('id', $id)->update([
                'status' => 'authorizing',
                'transaction_hash' => $transactionHash,
                'updated_at' => now(),
            ]);

            return $attempt;
        });

        if ($attempt->status === 'paid') {
            return $this->confirmedResult($attempt);
        }

        try {
            $response = Http::connectTimeout(5)
                ->timeout(40)
                ->withHeaders(['Authorization' => $this->accessToken()])
                ->post($this->baseUrl().'/api.authorization/v3/authorization/ecommerce/'.$attempt->merchant_id, [
                    'channel' => 'web',
                    'captureType' => 'manual',
                    'countable' => true,
                    'order' => [
                        'tokenId' => $transactionToken,
                        'purchaseNumber' => $attempt->purchase_number,
                        'amount' => $attempt->amount_cents / 100,
                        'currency' => 'PEN',
                    ],
                ]);
        } catch (Throwable $exception) {
            $this->markAttempt($id, 'unknown');
            Log::warning('Niubiz no confirmó el resultado de pago de una reserva.', [
                'attempt_id' => $id,
                'exception' => $exception::class,
            ]);

            return [
                'paid' => false,
                'message' => 'Niubiz todavía no confirmó el resultado. No vuelvas a pagar; el restaurante debe verificar la transacción.',
            ];
        }

        $actionCode = $response->json('dataMap.ACTION_CODE')
            ?? $response->json('data.ACTION_CODE')
            ?? $response->json('order.actionCode');
        $order = $response->json('order');
        $transactionId = is_array($order) ? ($order['transactionId'] ?? null) : null;
        $approved = $response->successful()
            && $actionCode === '000'
            && is_array($order)
            && ($order['currency'] ?? null) === 'PEN'
            && (string) ($order['purchaseNumber'] ?? '') === $attempt->purchase_number
            && (int) round((float) ($order['authorizedAmount'] ?? -1) * 100) === (int) $attempt->amount_cents
            && (string) $response->json('fulfillment.merchantId') === $attempt->merchant_id
            && is_string($transactionId)
            && strlen($transactionId) === 15;

        if (! $approved) {
            $declined = ! $response->serverError() && filled($actionCode) && $actionCode !== '000';
            $this->markAttempt($id, $declined ? 'declined' : 'unknown');

            return [
                'paid' => false,
                'message' => $declined
                    ? 'Niubiz rechazó el pago. No se confirmó la reserva; puedes intentarlo nuevamente.'
                    : 'No pudimos verificar la respuesta de Niubiz. No repitas el pago hasta confirmar su estado.',
            ];
        }

        try {
            $confirmation = Http::connectTimeout(5)
                ->timeout(30)
                ->withHeaders(['Authorization' => $this->accessToken()])
                ->post($this->baseUrl().'/api.confirmation/v1/confirmation/ecommerce/'.$attempt->merchant_id, [
                    'channel' => 'web',
                    'captureType' => 'manual',
                    'order' => [
                        'purchaseNumber' => $attempt->purchase_number,
                        'amount' => $attempt->amount_cents / 100,
                        'currency' => 'PEN',
                        'transactionId' => $transactionId,
                    ],
                ]);
        } catch (Throwable $exception) {
            $this->markAttempt($id, 'unknown', $transactionId);
            Log::warning('Niubiz no confirmó la captura manual de una reserva.', [
                'attempt_id' => $id,
                'exception' => $exception::class,
            ]);

            return [
                'paid' => false,
                'message' => 'El banco autorizó el pago, pero Niubiz no confirmó la captura. No repitas el pago; el restaurante verificará la operación.',
            ];
        }

        $confirmationData = $confirmation->json();
        $confirmationOrder = is_array($confirmationData) ? ($confirmationData['order'] ?? null) : null;
        $captured = $confirmation->successful()
            && is_array($confirmationData)
            && is_string($confirmationData['header']['ecoreTransactionUUID'] ?? null)
            && $confirmationData['header']['ecoreTransactionUUID'] !== ''
            && is_array($confirmationOrder)
            && ($confirmationOrder['actionCode'] ?? null) === '000'
            && (string) ($confirmationOrder['purchaseNumber'] ?? '') === $attempt->purchase_number
            && ($confirmationOrder['currency'] ?? null) === 'PEN'
            && (int) round((float) ($confirmationOrder['authorizedAmount'] ?? -1) * 100) === (int) $attempt->amount_cents;

        if (! $captured) {
            $this->markAttempt($id, 'unknown', $transactionId);
            Log::warning('Niubiz no confirmó la captura manual de una reserva.', [
                'attempt_id' => $id,
                'http_status' => $confirmation->status(),
            ]);

            return [
                'paid' => false,
                'message' => 'No pudimos verificar la captura del pago con Niubiz. No repitas el pago; el restaurante verificará la operación.',
            ];
        }

        try {
            $reservation = $this->reservations->confirmPayment(
                DB::table('reservation_holds')
                    ->where('id', $attempt->hold_id)
                    ->value('hold_token'),
                'niubiz',
                $transactionId,
                [
                    'transaction_id' => $transactionId,
                    'purchase_number' => $attempt->purchase_number,
                    'amount' => $order['authorizedAmount'],
                    'currency' => $order['currency'],
                    'action_code' => $actionCode,
                ]
            );
        } catch (Throwable $exception) {
            $this->markAttempt($id, 'review', $transactionId);
            Log::error('Pago de reserva aprobado por Niubiz requiere conciliación.', [
                'attempt_id' => $id,
                'exception' => $exception::class,
            ]);

            return [
                'paid' => false,
                'message' => 'Niubiz aprobó el cobro, pero no pudimos confirmar la reserva. El restaurante conciliará el pago; no vuelvas a pagar.',
            ];
        }

        if (! $reservation['ok']) {
            $this->markAttempt($id, 'review', $transactionId);

            return [
                'paid' => false,
                'message' => $reservation['message'].' El restaurante verificará el cobro; no vuelvas a pagar.',
            ];
        }

        $this->markAttempt($id, 'paid', $transactionId);

        return [
            'paid' => true,
            'message' => 'Pago aprobado por Niubiz y reserva confirmada.',
            'reservation' => $reservation,
        ];
    }

    /**
     * @return array{paid: true, message: string, reservation: array}
     */
    private function confirmedResult(object $attempt): array
    {
        $hold = DB::table('reservation_holds')->where('id', $attempt->hold_id)->first();
        $reservation = $hold?->reservation_id
            ? DB::table('reservations')->where('id', $hold->reservation_id)->first()
            : null;
        abort_unless($reservation, 409, 'El pago fue aprobado, pero no encontramos la reserva. Contacta al restaurante.');
        $tables = DB::table('restaurant_tables')
            ->join('reservation_tables', 'reservation_tables.table_id', '=', 'restaurant_tables.id')
            ->where('reservation_tables.reservation_id', $reservation->id)
            ->orderBy('restaurant_tables.table_number')
            ->get(['restaurant_tables.id', 'restaurant_tables.table_number', 'restaurant_tables.label', 'restaurant_tables.capacity'])
            ->all();

        return [
            'paid' => true,
            'message' => 'Pago aprobado por Niubiz y reserva confirmada.',
            'reservation' => [
                'id' => (int) $reservation->id,
                'reservation_code' => $reservation->reservation_code,
                'status' => $reservation->status,
                'customer_name' => $reservation->customer_name,
                'guest_count' => (int) $reservation->guest_count,
                'reservation_at' => CarbonImmutable::parse($reservation->reservation_at)->toIso8601String(),
                'arrival_deadline' => CarbonImmutable::parse($reservation->arrival_deadline)->toIso8601String(),
                'advance_amount' => (float) $reservation->advance_amount,
                'tables' => $tables,
            ],
        ];
    }

    private function markAttempt(string $id, string $status, ?string $reference = null): void
    {
        DB::table('reservation_payment_attempts')->where('id', $id)->update([
            'status' => $status,
            'provider_reference' => $reference,
            'session_key' => null,
            'updated_at' => now(),
        ]);
    }
}
