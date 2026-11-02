<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Services\NiubizReservationPayment;
use App\Services\ReservationAvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class ReservationApiController extends Controller
{
    public function disponibilidad(
        Request $request,
        ReservationAvailabilityService $service,
        NiubizReservationPayment $payments
    ): JsonResponse {
        $data = $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'time' => 'nullable|date_format:H:i',
            'people' => 'nullable|integer|min:1|max:40',
        ]);

        $res = $service->availability(
            $data['date'],
            $data['time'] ?? null,
            (int) ($data['people'] ?? 2)
        );

        return response()->json([
            ...$res,
            'niubiz_available' => $payments->available(),
        ])->header('Cache-Control', 'no-store');
    }

    public function cotizacion(Request $request, ReservationAvailabilityService $service): JsonResponse
    {
        $data = $request->validate([
            'people' => 'required|integer|min:1|max:40',
            'date' => 'required|date_format:Y-m-d',
            'time' => 'required|date_format:H:i',
        ]);

        $config = $service->config();
        $duration = $service->estimateDuration((int) $data['people']);

        return response()->json([
            'ok' => true,
            'advance_amount' => (float) $config->advance_amount,
            'currency' => 'PEN',
            'estimated_duration_minutes' => $duration,
            'tolerance_minutes' => (int) $config->tolerance_minutes,
            'hold_duration_minutes' => (int) $config->hold_duration_minutes,
            'policy' => [
                'advance_credit' => 'El anticipo de S/ 10.00 se descontará automáticamente de tu consumo final en el restaurante.',
                'tolerance' => "Cuentas con {$config->tolerance_minutes} minutos de tolerancia posterior a la hora reservada.",
                'cancellation' => "Cancelación con reembolso disponible hasta {$config->cancellation_deadline_hours} horas antes de la reserva.",
            ],
        ]);
    }

    public function verificarCliente(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => 'required|string|min:7|max:20',
        ]);

        $phone = preg_replace('/[^0-9]/', '', $data['phone']);
        $rateKey = 'otp_request:'.$phone.':'.$request->ip();

        if (RateLimiter::tooManyAttempts($rateKey, 4)) {
            $seconds = RateLimiter::availableIn($rateKey);

            return response()->json([
                'ok' => false,
                'message' => "Demasiados intentos. Espera {$seconds} segundos antes de solicitar otro código.",
            ], 429);
        }

        RateLimiter::hit($rateKey, 300);

        // Generar código de 6 dígitos
        $otp = (string) random_int(100000, 999999);
        $codeHash = Hash::make($otp);
        $expiresAt = now()->addMinutes(10);

        DB::table('customer_verification_codes')->insert([
            'phone' => $phone,
            'code_hash' => $codeHash,
            'attempts' => 0,
            'resends' => 1,
            'verified' => false,
            'ip_address' => $request->ip(),
            'expires_at' => $expiresAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Código de verificación generado.',
            'expires_at' => $expiresAt->toIso8601String(),
            // En entorno local / sandbox permitimos devolver el código para pruebas
            'debug_code' => config('app.debug') || app()->environment('testing', 'local') ? $otp : null,
        ]);
    }

    public function validarOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => 'required|string',
            'code' => 'required|digits:6',
        ]);

        $phone = preg_replace('/[^0-9]/', '', $data['phone']);
        $record = DB::table('customer_verification_codes')
            ->where('phone', $phone)
            ->where('verified', false)
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->first();

        if (! $record || ! Hash::check($data['code'], $record->code_hash)) {
            if ($record) {
                DB::table('customer_verification_codes')->where('id', $record->id)->increment('attempts');
            }

            return response()->json([
                'ok' => false,
                'message' => 'Código de verificación incorrecto o expirado.',
            ], 422);
        }

        DB::table('customer_verification_codes')->where('id', $record->id)->update([
            'verified' => true,
            'updated_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Teléfono verificado correctamente.',
        ]);
    }

    public function crearBloqueo(Request $request, ReservationAvailabilityService $service): JsonResponse
    {
        $result = $service->createHold($request->all(), $request->ip());

        return response()->json($result);
    }

    public function iniciarPago(Request $request, ReservationAvailabilityService $service): JsonResponse
    {
        $data = $request->validate([
            'hold_token' => 'required|string',
            'payment_method' => 'nullable|string|in:niubiz,direct,yape,plin,tarjeta',
        ]);

        $hold = DB::table('reservation_holds')->where('hold_token', $data['hold_token'])->first();
        abort_unless($hold, 404, 'Bloqueo de reserva no encontrado.');

        if ($hold->status === 'EXPIRED' || CarbonImmutable::parse($hold->expires_at)->lessThan(now())) {
            return response()->json([
                'ok' => false,
                'message' => 'El tiempo para completar el pago de este anticipo ha expirado. Por favor, selecciona tu horario nuevamente.',
            ], 409);
        }

        $config = $service->config();

        return response()->json([
            'ok' => true,
            'hold_token' => $hold->hold_token,
            'amount' => (float) $config->advance_amount,
            'currency' => 'PEN',
            'merchant_name' => 'DESEGO CEVICHERÍA PERUANA',
            'direct_payment' => config('dining.direct_payment'),
            'niubiz_available' => app(NiubizReservationPayment::class)->available(),
            'checkout_ready' => true,
        ]);
    }

    public function detalle(string $codigo): JsonResponse
    {
        $res = DB::table('reservations as r')
            ->where('r.reservation_code', $codigo)
            ->first();

        abort_unless($res, 404, 'Reserva no encontrada.');

        $tables = DB::table('restaurant_tables as t')
            ->where(function ($q) use ($res): void {
                $q->where('t.id', $res->table_id)
                    ->orWhereIn('t.id', DB::table('reservation_tables')->where('reservation_id', $res->id)->select('table_id'));
            })
            ->get(['t.table_number', 't.label', 't.capacity'])
            ->all();

        $payment = DB::table('reservation_payments')
            ->where('reservation_id', $res->id)
            ->orderByDesc('id')
            ->first();

        return response()->json([
            'ok' => true,
            'codigo' => $res->reservation_code,
            'cliente' => $res->customer_name,
            'personas' => (int) $res->guest_count,
            'fecha_hora' => $res->reservation_at,
            'limite_llegada' => $res->arrival_deadline,
            'estado' => $res->status,
            'anticipo' => (float) $res->advance_amount,
            'credito_aplicado' => (float) $res->applied_credit_amount,
            'mesas' => $tables,
            'pago' => $payment ? [
                'monto' => (float) $payment->amount,
                'estado' => $payment->status,
                'proveedor' => $payment->provider,
                'referencia' => $payment->external_reference,
            ] : null,
        ]);
    }

    public function cancelar(Request $request, string $codigo, ReservationAvailabilityService $service): JsonResponse
    {
        $data = $request->validate([
            'phone' => 'required|string',
            'reason' => 'nullable|string|max:300',
        ]);

        $res = DB::table('reservations')->where('reservation_code', $codigo)->first();
        abort_unless($res, 404, 'Reserva no encontrada.');

        $cleanPhoneInput = preg_replace('/[^0-9]/', '', $data['phone']);
        $cleanPhoneDb = preg_replace('/[^0-9]/', '', (string) $res->customer_phone);

        $matches = $cleanPhoneInput === $cleanPhoneDb
            || (strlen($cleanPhoneInput) >= 9 && strlen($cleanPhoneDb) >= 9 && substr($cleanPhoneInput, -9) === substr($cleanPhoneDb, -9));

        if (! $matches) {
            return response()->json([
                'ok' => false,
                'message' => 'El teléfono ingresado no coincide con el registrado en la reserva.',
            ], 403);
        }

        $result = $service->cancelReservation((int) $res->id, $data['reason'] ?? 'Cancelada por el cliente', false);

        return response()->json($result);
    }
}
