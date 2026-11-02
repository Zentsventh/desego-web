<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Services\DiningService;
use App\Services\ReservationAvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

use function App\Support\Desego\current_user;

class AdminReservationController extends Controller
{
    private function checkPermission(string|array $permissions): array
    {
        $user = current_user();
        abort_unless($user, 401, 'No autenticado.');
        $required = (array) $permissions;
        $userPermissions = $user['permissions'] ?? [];

        abort_unless(
            $user['role_code'] === 'admin' || ! empty(array_intersect($required, $userPermissions)),
            403,
            'No cuentas con permisos para esta acción.'
        );

        return $user;
    }

    public function index(Request $request): JsonResponse
    {
        $this->checkPermission(['reservations.view', 'reservations.manage']);

        $date = $request->query('date');
        $status = $request->query('status');
        $search = $request->query('search');

        $query = DB::table('reservations as r')
            ->leftJoin('restaurant_tables as t', 't.id', '=', 'r.table_id')
            ->select([
                'r.id',
                'r.reservation_code',
                'r.customer_name',
                'r.customer_phone',
                'r.customer_email',
                'r.table_id',
                't.label as table_label',
                'r.guest_count',
                'r.reservation_at',
                'r.duration_minutes',
                'r.status',
                'r.payment_status',
                'r.advance_amount',
                'r.applied_credit_amount',
                'r.arrival_deadline',
                'r.checked_in_at',
                'r.seated_at',
                'r.notes',
                'r.created_at',
            ]);

        if ($date) {
            $query->whereDate('r.reservation_at', $date);
        }

        if ($status) {
            $query->where('r.status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('r.reservation_code', 'LIKE', "%{$search}%")
                    ->orWhere('r.customer_name', 'LIKE', "%{$search}%")
                    ->orWhere('r.customer_phone', 'LIKE', "%{$search}%");
            });
        }

        $reservations = $query->orderByDesc('r.reservation_at')->limit(100)->get();

        return response()->json([
            'ok' => true,
            'reservations' => $reservations,
        ]);
    }

    public function calendario(Request $request): JsonResponse
    {
        $this->checkPermission(['reservations.view', 'reservations.manage']);

        $request->merge([
            'from' => $request->query('from', now('America/Lima')->toDateString()),
            'to' => $request->query('to', now('America/Lima')->addDays(6)->toDateString()),
        ]);
        $dates = $request->validate([
            'from' => 'required|date_format:Y-m-d',
            'to' => 'required|date_format:Y-m-d|after_or_equal:from',
        ]);

        $fromDate = CarbonImmutable::createFromFormat('!Y-m-d', $dates['from'], 'America/Lima');
        $toDate = CarbonImmutable::createFromFormat('!Y-m-d', $dates['to'], 'America/Lima');

        if ($fromDate->diffInDays($toDate) > 30) {
            throw ValidationException::withMessages([
                'to' => 'El calendario admite rangos de hasta 31 días.',
            ]);
        }

        $from = $fromDate->utc();
        $to = $toDate->endOfDay()->utc();

        $reservations = DB::table('reservations as r')
            ->leftJoin('restaurant_tables as t', 't.id', '=', 'r.table_id')
            ->where('r.reservation_at', '>=', $from)
            ->where('r.reservation_at', '<=', $to)
            ->whereNotIn('r.status', ['Cancelada', 'CANCELLED', 'EXPIRED'])
            ->select([
                'r.id',
                'r.reservation_code',
                'r.customer_name',
                'r.customer_phone',
                'r.guest_count',
                'r.table_id',
                't.label as table_label',
                'r.reservation_at',
                'r.duration_minutes',
                'r.status',
                'r.payment_status',
                'r.advance_amount',
            ])
            ->orderBy('r.reservation_at')
            ->get()
            ->map(function ($r) {
                return [
                    'id' => (int) $r->id,
                    'reservation_code' => $r->reservation_code,
                    'customer_name' => $r->customer_name,
                    'customer_phone' => $r->customer_phone,
                    'guest_count' => (int) $r->guest_count,
                    'table_id' => $r->table_id ? (int) $r->table_id : null,
                    'table_label' => $r->table_label,
                    'reservation_at' => CarbonImmutable::parse($r->reservation_at)->toIso8601String(),
                    'duration_minutes' => (int) ($r->duration_minutes ?? 120),
                    'status' => $r->status,
                    'payment_status' => $r->payment_status,
                    'advance_amount' => $r->advance_amount,
                ];
            });

        return response()->json([
            'ok' => true,
            'events' => $reservations,
        ]);
    }

    public function checkIn(int $id, ReservationAvailabilityService $service): JsonResponse
    {
        $user = $this->checkPermission('reservations.manage');
        $result = $service->checkIn($id, $user['id'] ?? null);

        return response()->json($result);
    }

    public function asignarMesa(Request $request, int $id, ReservationAvailabilityService $service): JsonResponse
    {
        $user = $this->checkPermission('reservations.manage');
        $data = $request->validate([
            'table_ids' => 'required|array|min:1',
            'table_ids.*' => 'required|integer',
        ]);

        return DB::transaction(function () use ($id, $data, $user, $service): JsonResponse {
            $res = DB::table('reservations')->where('id', $id)->lockForUpdate()->first();
            abort_unless($res, 404, 'Reserva no encontrada.');

            $tableIds = array_map('intval', $data['table_ids']);
            sort($tableIds);

            // Verificar disponibilidad de las nuevas mesas
            $startUtc = CarbonImmutable::parse($res->reservation_at);
            foreach ($tableIds as $tid) {
                if ($service->overlaps($tid, $startUtc, (int) $res->duration_minutes, $res->id)) {
                    throw ValidationException::withMessages([
                        'table_ids' => "La mesa ID {$tid} tiene conflictos con otra reserva en ese horario.",
                    ]);
                }
            }

            DB::table('reservations')->where('id', $res->id)->update([
                'table_id' => $tableIds[0],
                'updated_at' => now(),
            ]);

            DB::table('reservation_tables')->where('reservation_id', $res->id)->delete();
            foreach ($tableIds as $tid) {
                DB::table('reservation_tables')->insert([
                    'reservation_id' => $res->id,
                    'table_id' => $tid,
                ]);
            }

            DB::table('reservation_audit_logs')->insert([
                'reservation_id' => $res->id,
                'user_id' => $user['id'] ?? null,
                'action' => 'table_reassigned',
                'details' => json_encode(['table_ids' => $tableIds]),
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'Mesas reasignadas correctamente.',
            ]);
        });
    }

    public function sentar(Request $request, int $id, ReservationAvailabilityService $service, DiningService $dining): JsonResponse
    {
        $user = $this->checkPermission(['reservations.manage', 'tables.manage']);
        $res = DB::table('reservations')->where('id', $id)->first();
        abort_unless($res, 404, 'Reserva no encontrada.');

        // Si se provee session_id existente o si se debe abrir una visita para la mesa asignada
        $sessionId = $request->input('session_id');

        if (! $sessionId) {
            $table = DB::table('restaurant_tables')->where('id', $res->table_id)->first();
            abort_unless($table, 404, 'Mesa no encontrada.');

            $activeSession = DB::table('dining_sessions')->where('active_table_id', $table->id)->first();
            if ($activeSession) {
                $sessionId = $activeSession->id;
            } else {
                // Crear sesión abierta para la mesa
                $code = (string) random_int(100000, 999999);
                $newSessionId = (string) Str::uuid();
                DB::table('dining_sessions')->insert([
                    'id' => $newSessionId,
                    'table_id' => $table->id,
                    'active_table_id' => $table->id,
                    'status' => 'open',
                    'join_code_hash' => Hash::make($code),
                    'join_code_encrypted' => Crypt::encryptString($code),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $sessionId = $newSessionId;
            }
        }

        $result = $service->seatAndLinkOrder($id, $sessionId, $user['id'] ?? null);

        return response()->json($result);
    }

    public function finalizar(int $id): JsonResponse
    {
        $user = $this->checkPermission('reservations.manage');

        return DB::transaction(function () use ($id, $user): JsonResponse {
            $res = DB::table('reservations')->where('id', $id)->lockForUpdate()->first();
            abort_unless($res, 404, 'Reserva no encontrada.');

            DB::table('reservations')->where('id', $res->id)->update([
                'status' => 'COMPLETED',
                'updated_at' => now(),
            ]);

            DB::table('reservation_audit_logs')->insert([
                'reservation_id' => $res->id,
                'user_id' => $user['id'] ?? null,
                'action' => 'completed',
                'details' => json_encode(['completed_at' => now()->toIso8601String()]),
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'Atención de la reserva marcada como completada.',
            ]);
        });
    }

    public function cancelar(Request $request, int $id, ReservationAvailabilityService $service): JsonResponse
    {
        $user = $this->checkPermission('reservations.manage');
        $data = $request->validate([
            'reason' => 'required|string|max:300',
        ]);

        $result = $service->cancelReservation($id, $data['reason'], true, $user['id'] ?? null);

        return response()->json($result);
    }

    public function reembolsar(Request $request, int $id): JsonResponse
    {
        $user = $this->checkPermission('payments.manage');
        $data = $request->validate([
            'reason' => 'required|string|max:255',
            'amount' => 'nullable|numeric|decimal:0,2|min:0.01',
        ]);

        return DB::transaction(function () use ($id, $data, $user): JsonResponse {
            $res = DB::table('reservations')->where('id', $id)->lockForUpdate()->first();
            abort_unless($res, 404, 'Reserva no encontrada.');

            if (! in_array($res->payment_status, ['PAID', 'PARTIALLY_REFUNDED'], true)) {
                throw ValidationException::withMessages([
                    'amount' => 'La reserva no tiene un pago confirmado pendiente de devolución.',
                ]);
            }

            $payments = DB::table('reservation_payments')
                ->where('reservation_id', $res->id)
                ->whereIn('status', ['PAID', 'PARTIALLY_REFUNDED'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $availableCents = $payments->sum(
                fn (object $payment): int => max(
                    0,
                    (int) round((float) $payment->amount * 100) - (int) round((float) $payment->refund_amount * 100)
                )
            );

            if ($availableCents <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'No existe un pago confirmado con saldo disponible para devolver.',
                ]);
            }

            $refundCents = isset($data['amount'])
                ? (int) round((float) $data['amount'] * 100)
                : $availableCents;

            if ($refundCents > $availableCents) {
                throw ValidationException::withMessages([
                    'amount' => 'El monto no puede superar el saldo pagado pendiente de devolución.',
                ]);
            }

            $remainingRefundCents = $refundCents;
            foreach ($payments as $payment) {
                if ($remainingRefundCents <= 0) {
                    break;
                }

                $paymentAmountCents = (int) round((float) $payment->amount * 100);
                $alreadyRefundedCents = (int) round((float) $payment->refund_amount * 100);
                $paymentAvailableCents = max(0, $paymentAmountCents - $alreadyRefundedCents);
                $paymentRefundCents = min($remainingRefundCents, $paymentAvailableCents);

                if ($paymentRefundCents <= 0) {
                    continue;
                }

                $newRefundedCents = $alreadyRefundedCents + $paymentRefundCents;
                $isFullyRefunded = $newRefundedCents >= $paymentAmountCents;

                DB::table('reservation_payments')->where('id', $payment->id)->update([
                    'status' => $isFullyRefunded ? 'REFUNDED' : 'PARTIALLY_REFUNDED',
                    'refunded_at' => now(),
                    'refund_amount' => number_format($newRefundedCents / 100, 2, '.', ''),
                    'refund_reason' => $data['reason'],
                    'refunded_by' => $user['id'] ?? null,
                    'updated_at' => now(),
                ]);

                $remainingRefundCents -= $paymentRefundCents;
            }

            $isFullyRefunded = $refundCents === $availableCents;
            $refundAmount = number_format($refundCents / 100, 2, '.', '');

            DB::table('reservations')->where('id', $res->id)->update([
                'payment_status' => $isFullyRefunded ? 'REFUNDED' : 'PARTIALLY_REFUNDED',
                'updated_at' => now(),
            ]);

            DB::table('reservation_audit_logs')->insert([
                'reservation_id' => $res->id,
                'user_id' => $user['id'] ?? null,
                'action' => 'manual_refund_authorized',
                'details' => json_encode([
                    'refund_amount' => $refundAmount,
                    'reason' => $data['reason'],
                ]),
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);

            return response()->json([
                'ok' => true,
                'message' => ($isFullyRefunded ? 'Devolución completada' : 'Devolución parcial registrada')." por S/ {$refundAmount}.",
            ]);
        });
    }

    public function mesas(): JsonResponse
    {
        $this->checkPermission(['tables.view', 'reservations.view']);

        $tables = DB::table('restaurant_tables as t')
            ->leftJoin('reservation_table_settings as s', 's.table_id', '=', 't.id')
            ->orderBy('t.table_number')
            ->get(['t.id', 't.table_number', 't.label', 't.zone', 't.capacity', 't.operational_status', 't.is_active', 't.is_online_reservable', 's.join_group'])
            ->map(function ($table) {
                // Verificar si tiene sesión activa de salón
                $session = DB::table('dining_sessions')->where('active_table_id', $table->id)->first();
                $isCleaning = $session && $session->status === 'cleaning';
                $isDiningActive = $session && in_array($session->status, ['open', 'checkout', 'paid'], true);

                // Próxima reserva confirmada hoy o vigente
                $nextReservation = DB::table('reservations as r')
                    ->where(function ($q) use ($table): void {
                        $q->where('r.table_id', $table->id)
                            ->orWhereExists(function ($sub) use ($table): void {
                                $sub->selectRaw('1')->from('reservation_tables as rt')
                                    ->whereColumn('rt.reservation_id', 'r.id')
                                    ->where('rt.table_id', $table->id);
                            });
                    })
                    ->whereIn('r.status', ['Confirmada', 'CONFIRMED', 'CHECKED_IN'])
                    ->whereRaw('DATE_ADD(r.reservation_at, INTERVAL COALESCE(r.duration_minutes, 120) MINUTE) >= ?', [now()])
                    ->orderBy('r.reservation_at')
                    ->first(['r.id', 'r.reservation_code', 'r.customer_name', 'r.guest_count', 'r.reservation_at', 'r.duration_minutes', 'r.arrival_deadline', 'r.status']);

                $status = $table->operational_status;
                if (! $table->is_active) {
                    $status = 'disabled';
                } elseif ($table->operational_status === 'maintenance') {
                    $status = 'maintenance';
                } elseif ($isCleaning || $table->operational_status === 'cleaning') {
                    $status = 'cleaning';
                } elseif ($isDiningActive || $table->operational_status === 'occupied') {
                    $status = 'occupied';
                } elseif ($nextReservation) {
                    $resTime = CarbonImmutable::parse($nextReservation->reservation_at);
                    $duration = (int) ($nextReservation->duration_minutes ?? 120);
                    $resEndTime = $resTime->addMinutes($duration);
                    if (now()->greaterThanOrEqualTo($resTime->subMinutes(45)) && now()->lessThan($resEndTime)) {
                        $status = 'reserved';
                    }
                }

                $formattedReservation = null;
                if ($nextReservation) {
                    $formattedReservation = [
                        'id' => (int) $nextReservation->id,
                        'reservation_code' => $nextReservation->reservation_code,
                        'customer_name' => $nextReservation->customer_name,
                        'guest_count' => (int) $nextReservation->guest_count,
                        'reservation_at' => CarbonImmutable::parse($nextReservation->reservation_at)->toIso8601String(),
                        'duration_minutes' => (int) ($nextReservation->duration_minutes ?? 120),
                        'status' => $nextReservation->status,
                    ];
                }

                return [
                    'id' => (int) $table->id,
                    'table_number' => (int) $table->table_number,
                    'label' => $table->label,
                    'zone' => $table->zone,
                    'capacity' => (int) $table->capacity,
                    'operational_status' => $table->operational_status,
                    'effective_status' => $status,
                    'is_active' => (bool) $table->is_active,
                    'is_online_reservable' => (bool) ($table->is_online_reservable ?? true),
                    'join_group' => $table->join_group,
                    'next_reservation' => $formattedReservation,
                    'active_session' => $session ? ['id' => $session->id, 'status' => $session->status] : null,
                ];
            });

        return response()->json([
            'ok' => true,
            'tables' => $tables,
        ]);
    }

    public function actualizarMesa(Request $request, int $id): JsonResponse
    {
        $user = $this->checkPermission('tables.manage');
        $data = $request->validate([
            'capacity' => 'nullable|integer|min:1|max:20',
            'is_active' => 'nullable|boolean',
            'is_online_reservable' => 'nullable|boolean',
            'operational_status' => 'nullable|string|in:available,occupied,cleaning,maintenance',
            'join_group' => 'nullable|string|max:60',
        ]);

        DB::transaction(function () use ($id, $data, $user): void {
            $table = DB::table('restaurant_tables')->where('id', $id)->lockForUpdate()->first();
            abort_unless($table, 404, 'Mesa no encontrada.');

            $updates = ['updated_at' => now()];
            if (isset($data['capacity'])) {
                $updates['capacity'] = $data['capacity'];
            }
            if (isset($data['is_active'])) {
                $updates['is_active'] = $data['is_active'];
            }
            if (isset($data['is_online_reservable'])) {
                $updates['is_online_reservable'] = $data['is_online_reservable'];
            }
            if (isset($data['operational_status'])) {
                $updates['operational_status'] = $data['operational_status'] === 'cleaning' ? 'available' : $data['operational_status'];
                // Si se marca cleaning o available, actualizar sesión de mesa si existe
                if ($data['operational_status'] === 'cleaning') {
                    DB::table('dining_sessions')->where('active_table_id', $table->id)->update([
                        'status' => 'cleaning',
                        'updated_at' => now(),
                    ]);
                } elseif ($data['operational_status'] === 'available') {
                    DB::table('dining_sessions')->where('active_table_id', $table->id)->update([
                        'status' => 'closed',
                        'active_table_id' => null,
                        'closed_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::table('restaurant_tables')->where('id', $table->id)->update($updates);

            if (array_key_exists('join_group', $data)) {
                DB::table('reservation_table_settings')->updateOrInsert(
                    ['table_id' => $table->id],
                    ['join_group' => $data['join_group']]
                );
            }

            DB::table('reservation_audit_logs')->insert([
                'reservation_id' => null,
                'user_id' => $user['id'] ?? null,
                'action' => 'table_updated',
                'details' => json_encode(['table_id' => $table->id, 'changes' => $data]),
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);
        });

        return response()->json([
            'ok' => true,
            'message' => 'Mesa actualizada correctamente.',
        ]);
    }

    public function crearMesa(Request $request): JsonResponse
    {
        $user = $this->checkPermission('tables.manage');
        $data = $request->validate([
            'table_number' => 'nullable|integer|min:1|max:100',
            'label' => 'nullable|string|max:40',
            'zone' => 'nullable|string|max:60',
            'capacity' => 'required|integer|min:1|max:20',
            'join_group' => 'nullable|string|max:60',
        ]);

        return DB::transaction(function () use ($data, $user): JsonResponse {
            $maxNum = (int) DB::table('restaurant_tables')->max('table_number');
            $nextNumber = isset($data['table_number']) ? (int) $data['table_number'] : ($maxNum + 1);
            $label = $data['label'] ?? sprintf('Mesa %02d', $nextNumber);
            $zone = $data['zone'] ?? 'Salón principal';

            $tableId = DB::table('restaurant_tables')->insertGetId([
                'table_number' => $nextNumber,
                'label' => $label,
                'zone' => $zone,
                'capacity' => (int) $data['capacity'],
                'operational_status' => 'available',
                'is_active' => true,
                'is_online_reservable' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (! empty($data['join_group'])) {
                DB::table('reservation_table_settings')->insert([
                    'table_id' => $tableId,
                    'join_group' => $data['join_group'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('reservation_audit_logs')->insert([
                'reservation_id' => null,
                'user_id' => $user['id'] ?? null,
                'action' => 'table_created',
                'details' => json_encode(['table_id' => $tableId, 'table_number' => $nextNumber, 'capacity' => $data['capacity']]),
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);

            return response()->json([
                'ok' => true,
                'message' => "Mesa {$label} creada correctamente.",
                'table_id' => $tableId,
            ]);
        });
    }

    public function configuracion(ReservationAvailabilityService $service): JsonResponse
    {
        $this->checkPermission(['reservations.view', 'reservations.manage']);

        $config = $service->config();

        return response()->json([
            'ok' => true,
            'config' => $config,
            'configuration' => $config,
        ]);
    }

    public function actualizarConfiguracion(Request $request, ReservationAvailabilityService $service): JsonResponse
    {
        $user = $this->checkPermission('reservations.manage');
        $data = $request->validate([
            'advance_amount' => 'required|numeric|min:0|max:1000',
            'tolerance_minutes' => 'required|integer|min:5|max:60',
            'hold_duration_minutes' => 'required|integer|min:3|max:30',
            'cleaning_minutes' => 'required|integer|min:0|max:60',
            'max_active_reservations_per_phone' => 'required|integer|min:1|max:10',
            'max_advance_days' => 'required|integer|min:1|max:90',
            'min_advance_minutes' => 'required|integer|min:0|max:360',
            'reservations_enabled' => 'required|boolean',
            'cancellation_deadline_hours' => 'required|integer|min:0|max:48',
        ]);

        $config = $service->config();

        DB::table('reservation_configurations')->where('id', $config->id)->update([
            ...$data,
            'updated_at' => now(),
        ]);

        DB::table('reservation_audit_logs')->insert([
            'reservation_id' => null,
            'user_id' => $user['id'] ?? null,
            'action' => 'configuration_updated',
            'details' => json_encode($data),
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);

        $updatedConfig = $service->config();

        return response()->json([
            'ok' => true,
            'config' => $updatedConfig,
            'configuration' => $updatedConfig,
            'message' => 'Configuración de reservas actualizada correctamente.',
        ]);
    }
}
