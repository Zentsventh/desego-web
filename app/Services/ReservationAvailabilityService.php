<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

use function App\Support\Desego\audit_log;
use function App\Support\Desego\desego_db;

class ReservationAvailabilityService
{
    public function config(): object
    {
        $config = DB::table('reservation_configurations')->orderBy('id')->first();
        if (! $config) {
            DB::table('reservation_configurations')->insert([
                'advance_amount' => 10.00,
                'tolerance_minutes' => 15,
                'hold_duration_minutes' => 10,
                'cleaning_minutes' => 15,
                'max_active_reservations_per_phone' => 2,
                'max_advance_days' => 30,
                'min_advance_minutes' => 30,
                'reservations_enabled' => true,
                'cancellation_deadline_hours' => 2,
                'duration_rules' => json_encode([
                    '1-2' => 60,
                    '3-4' => 90,
                    '5-6' => 120,
                    '7+' => 150,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $config = DB::table('reservation_configurations')->orderBy('id')->first();
        }

        return $config;
    }

    public function estimateDuration(int $guests): int
    {
        $config = $this->config();
        $rules = json_decode((string) ($config->duration_rules ?? ''), true) ?: [
            '1-2' => 60,
            '3-4' => 90,
            '5-6' => 120,
            '7+' => 150,
        ];

        if ($guests <= 2) {
            return (int) ($rules['1-2'] ?? 60);
        }
        if ($guests <= 4) {
            return (int) ($rules['3-4'] ?? 90);
        }
        if ($guests <= 6) {
            return (int) ($rules['5-6'] ?? 120);
        }

        return (int) ($rules['7+'] ?? 150);
    }

    public function schedule(string $date): array
    {
        Validator::make(['date' => $date], ['date' => 'required|date_format:Y-m-d'])->validate();
        $now = CarbonImmutable::now('America/Lima');
        $config = $this->config();

        if ($date < $now->toDateString()) {
            throw ValidationException::withMessages(['date' => 'Elige hoy o una fecha futura.']);
        }

        $maxDate = $now->addDays((int) $config->max_advance_days)->toDateString();
        if ($date > $maxDate) {
            throw ValidationException::withMessages(['date' => "Solo puedes reservar con hasta {$config->max_advance_days} días de anticipación."]);
        }

        $branch = DB::table('branches')->where('status', 'active')->orderByDesc('is_main')->first() ?? DB::table('branches')->orderByDesc('is_main')->first();
        abort_unless($branch, 503, 'El restaurante aún no recibe reservas.');

        $hasHours = $branch->opening_time && $branch->closing_time;
        $opening = CarbonImmutable::parse($date.' '.($hasHours ? $branch->opening_time : '00:00:00'), 'America/Lima');
        $closing = $hasHours ? CarbonImmutable::parse($date.' '.$branch->closing_time, 'America/Lima') : $opening->addDay();
        if ($closing->lessThanOrEqualTo($opening)) {
            $closing = $closing->addDay();
        }

        $slots = [];
        $slotDuration = 120;
        for ($slot = $opening; $slot->addMinutes($slotDuration)->lessThanOrEqualTo($closing); $slot = $slot->addMinutes(30)) {
            if ($slot->toDateString() === $date && ($date > $now->toDateString() || $slot->greaterThanOrEqualTo($now))) {
                $slots[] = $slot->format('H:i');
            }
        }

        return [
            'today' => $now->toDateString(),
            'server_now' => $now->toIso8601String(),
            'slots' => $slots,
            'opening' => $hasHours ? substr((string) $branch->opening_time, 0, 5) : null,
            'closing' => $hasHours ? substr((string) $branch->closing_time, 0, 5) : null,
            'advance_amount' => (float) $config->advance_amount,
            'tolerance_minutes' => (int) $config->tolerance_minutes,
            'reservations_enabled' => (bool) $config->reservations_enabled,
        ];
    }

    public function start(string $date, string $time): CarbonImmutable
    {
        $schedule = $this->schedule($date);
        if (! in_array($time, $schedule['slots'], true)) {
            throw ValidationException::withMessages(['time' => 'Elige un horario disponible dentro del horario de atención.']);
        }

        return CarbonImmutable::createFromFormat('!Y-m-d H:i', $date.' '.$time, 'America/Lima')->utc();
    }

    public function overlaps(
        int $tableId,
        CarbonImmutable $newStartUtc,
        int $newDurationMinutes,
        ?int $exceptReservationId = null,
        ?string $exceptHoldToken = null
    ): bool {
        $config = $this->config();
        $cleaningMinutes = (int) ($config->cleaning_minutes ?? 15);
        $newEnd = $newStartUtc->addMinutes($newDurationMinutes);

        // 1. Conflictos con reservas confirmadas o activas
        $reservationConflict = DB::table('reservations as r')
            ->where(function ($query) use ($tableId): void {
                $query->where('r.table_id', $tableId)
                    ->orWhereExists(function ($sub) use ($tableId): void {
                        $sub->selectRaw('1')
                            ->from('reservation_tables as rt')
                            ->whereColumn('rt.reservation_id', 'r.id')
                            ->where('rt.table_id', $tableId);
                    });
            })
            ->when($exceptReservationId, fn ($q) => $q->where('r.id', '<>', $exceptReservationId))
            ->whereIn('r.status', ['Pendiente', 'Confirmada', 'Sentada', 'CONFIRMED', 'CHECKED_IN', 'IN_SERVICE'])
            ->where(function ($q) use ($newStartUtc, $newEnd, $cleaningMinutes): void {
                $q->where('r.reservation_at', '<', $newEnd)
                    ->whereRaw("DATE_ADD(r.reservation_at, INTERVAL (r.duration_minutes + CASE WHEN r.status IN ('CONFIRMED', 'CHECKED_IN', 'IN_SERVICE') THEN ? ELSE 0 END) MINUTE) > ?", [
                        $cleaningMinutes,
                        $newStartUtc,
                    ]);
            })
            ->exists();

        if ($reservationConflict) {
            return true;
        }

        // 2. Conflictos con bloqueos temporales activos de pago (reservation_holds)
        $nowUtc = CarbonImmutable::now('UTC');
        $holdConflict = DB::table('reservation_holds')
            ->where('status', 'ACTIVE')
            ->where('expires_at', '>', $nowUtc)
            ->when($exceptHoldToken, fn ($q) => $q->where('hold_token', '<>', $exceptHoldToken))
            ->where(function ($q) use ($tableId): void {
                $q->whereJsonContains('table_ids', (int) $tableId)
                    ->orWhereJsonContains('table_ids', (string) $tableId);
            })
            ->where(function ($q) use ($newStartUtc, $newEnd, $cleaningMinutes): void {
                $q->where('reservation_at', '<', $newEnd)
                    ->whereRaw('DATE_ADD(reservation_at, INTERVAL (duration_minutes + ?) MINUTE) > ?', [
                        $cleaningMinutes,
                        $newStartUtc,
                    ]);
            })
            ->exists();

        return $holdConflict;
    }

    public function unavailable(object $table, CarbonImmutable $startUtc, int $durationMinutes = 90, ?int $exceptReservationId = null): bool
    {
        if (! $table->is_active) {
            return true;
        }

        if (property_exists($table, 'is_online_reservable') && ! $table->is_online_reservable) {
            return true;
        }

        if ($table->operational_status === 'maintenance' || $table->operational_status === 'disabled') {
            return true;
        }

        // Si la mesa está ocupada físicamente y la nueva reserva comienza pronto
        if ($table->operational_status === 'occupied' && $startUtc->lessThanOrEqualTo(CarbonImmutable::now('UTC')->addMinutes(120))) {
            return true;
        }

        return $this->overlaps((int) $table->id, $startUtc, $durationMinutes, $exceptReservationId);
    }

    public function availability(string $date, ?string $time, int $guests = 2): array
    {
        $schedule = $this->schedule($date);
        $time = in_array($time, $schedule['slots'], true) ? $time : null;
        $tables = [];
        $durationMinutes = $this->estimateDuration($guests);

        if ($time) {
            $startUtc = $this->start($date, $time);

            $tables = DB::table('restaurant_tables as t')
                ->leftJoin('reservation_table_settings as s', 's.table_id', '=', 't.id')
                ->where('t.is_active', 1)
                ->orderBy('t.table_number')
                ->get(['t.id', 't.table_number', 't.label', 't.zone', 't.capacity', 't.is_active', 't.operational_status', 't.is_online_reservable', 's.join_group'])
                ->map(function (object $table) use ($startUtc, $durationMinutes): array {
                    $isUnavailable = $this->unavailable($table, $startUtc, $durationMinutes);

                    return [
                        'id' => (int) $table->id,
                        'table_number' => (int) $table->table_number,
                        'label' => $table->label,
                        'zone' => $table->zone,
                        'capacity' => (int) $table->capacity,
                        'join_group' => $table->join_group,
                        'operational_status' => $table->operational_status,
                        'available' => ! $isUnavailable,
                    ];
                })
                ->all();
        }

        return [
            'ok' => true,
            ...$schedule,
            'time' => $time,
            'guests' => $guests,
            'duration_minutes' => $durationMinutes,
            'tables' => $tables,
        ];
    }

    public function findCompatibleTables(int $guests, CarbonImmutable $startUtc, int $durationMinutes, ?array $preferredTableIds = null, ?int $exceptReservationId = null): array
    {
        $query = DB::table('restaurant_tables as t')
            ->leftJoin('reservation_table_settings as s', 's.table_id', '=', 't.id')
            ->where('t.is_active', 1)
            ->where('t.operational_status', 'available')
            ->where(function ($q): void {
                $q->whereNull('t.is_online_reservable')->orWhere('t.is_online_reservable', 1);
            });

        if ($preferredTableIds) {
            $tables = (clone $query)->whereIn('t.id', $preferredTableIds)->orderBy('t.id')->get();
            if ($tables->count() !== count($preferredTableIds)) {
                throw ValidationException::withMessages(['table_ids' => 'Alguna de las mesas seleccionadas no está disponible.']);
            }
            if ($tables->sum('capacity') < $guests) {
                throw ValidationException::withMessages(['table_ids' => 'La capacidad total seleccionada no cubre el número de personas.']);
            }
            foreach ($tables as $t) {
                if ($this->unavailable($t, $startUtc, $durationMinutes, $exceptReservationId)) {
                    throw ValidationException::withMessages(['table_ids' => "La mesa {$t->label} ya no está disponible para ese horario."]);
                }
            }

            return $tables->all();
        }

        // Asignación automática:
        // 1. Buscar una sola mesa cuya capacidad sea >= a las personas
        $single = (clone $query)
            ->where('t.capacity', '>=', $guests)
            ->orderBy('t.capacity')
            ->orderBy('t.table_number')
            ->get();

        foreach ($single as $candidate) {
            if (! $this->unavailable($candidate, $startUtc, $durationMinutes, $exceptReservationId)) {
                return [$candidate];
            }
        }

        // 2. Si son más de 4 personas o no hay una sola mesa, buscar unión de mesas contiguas con join_group
        if ($guests > 4) {
            $allCandidates = (clone $query)->whereNotNull('s.join_group')->orderBy('t.table_number')->get();
            $grouped = $allCandidates->groupBy('join_group');

            foreach ($grouped as $groupTables) {
                $availableInGroup = $groupTables->filter(fn ($tbl) => ! $this->unavailable($tbl, $startUtc, $durationMinutes, $exceptReservationId));
                if ($availableInGroup->sum('capacity') >= $guests) {
                    return $availableInGroup->values()->all();
                }
            }
        }

        throw ValidationException::withMessages([
            'table_ids' => 'No encontramos mesas disponibles con capacidad suficiente para este horario. Puedes unirte a la lista de espera.',
        ]);
    }

    public function validateAntiFraud(string $phone, CarbonImmutable $startUtc): void
    {
        $config = $this->config();
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);

        // Regla B: Límite de reservas futuras activas por teléfono
        $activeReservationsCount = DB::table('reservations')
            ->where(function ($q) use ($cleanPhone, $phone): void {
                $q->where('customer_phone', $phone)
                    ->orWhereRaw("REGEXP_REPLACE(customer_phone, '[^0-9]', '') = ?", [$cleanPhone]);
            })
            ->whereIn('status', ['Pendiente', 'Confirmada', 'CONFIRMED', 'CHECKED_IN'])
            ->where('reservation_at', '>=', CarbonImmutable::now('UTC'))
            ->count();

        if ($activeReservationsCount >= (int) $config->max_active_reservations_per_phone) {
            throw ValidationException::withMessages([
                'phone' => "Ya cuentas con {$activeReservationsCount} reservas activas próximas. Comunícate directamente con recepción para solicitudes adicionales.",
            ]);
        }

        // Regla A: Sin reservas simultáneas superpuestas para el mismo teléfono
        $overlapCount = DB::table('reservations')
            ->where(function ($q) use ($cleanPhone, $phone): void {
                $q->where('customer_phone', $phone)
                    ->orWhereRaw("REGEXP_REPLACE(customer_phone, '[^0-9]', '') = ?", [$cleanPhone]);
            })
            ->whereIn('status', ['Pendiente', 'Confirmada', 'CONFIRMED', 'CHECKED_IN', 'IN_SERVICE'])
            ->where('reservation_at', '<', $startUtc->addMinutes(120))
            ->whereRaw('DATE_ADD(reservation_at, INTERVAL duration_minutes MINUTE) > ?', [$startUtc])
            ->count();

        if ($overlapCount > 0) {
            throw ValidationException::withMessages([
                'phone' => 'Ya tienes otra reserva registrada en ese mismo horario.',
            ]);
        }
    }

    public function createHold(array $input, ?string $ip = null): array
    {
        $data = Validator::make($input, [
            'customer_name' => 'required|string|min:3|max:100',
            'phone' => 'required|string|max:30',
            'email' => 'nullable|email|max:190',
            'date' => 'required|date_format:Y-m-d',
            'time' => 'required|date_format:H:i',
            'people' => 'required|integer|min:1|max:40',
            'notes' => 'nullable|string|max:500',
            'table_ids' => 'sometimes|nullable|array|min:1|max:50',
            'table_ids.*' => 'required|integer|distinct|min:1',
        ])->validate();

        $startUtc = $this->start($data['date'], $data['time']);
        $durationMinutes = $this->estimateDuration((int) $data['people']);
        $this->validateAntiFraud($data['phone'], $startUtc);

        $config = $this->config();
        abort_unless($config->reservations_enabled, 503, 'El servicio de reservas se encuentra temporalmente deshabilitado.');

        // Bloqueo de concurrencia y validación atómica
        return DB::transaction(function () use ($data, $startUtc, $durationMinutes, $config, $ip): array {
            // Expira holds viejos primero
            $this->expireStaleHolds();

            // Bloquear mesas candidatas en orden determinista de ID para prevenir deadlocks
            $preferredIds = ! empty($data['table_ids']) ? array_map('intval', $data['table_ids']) : null;
            if ($preferredIds) {
                sort($preferredIds);
                DB::table('restaurant_tables')->whereIn('id', $preferredIds)->lockForUpdate()->get();
            }

            $tables = $this->findCompatibleTables((int) $data['people'], $startUtc, $durationMinutes, $preferredIds);
            $tableIds = array_map(fn ($t) => (int) $t->id, $tables);
            sort($tableIds);

            // Rebloquear las mesas asignadas
            DB::table('restaurant_tables')->whereIn('id', $tableIds)->lockForUpdate()->get();

            // Verificar una vez más que ninguna esté superpuesta
            foreach ($tableIds as $tid) {
                if ($this->overlaps($tid, $startUtc, $durationMinutes)) {
                    throw ValidationException::withMessages([
                        'table_ids' => 'Una de las mesas requeridas acaba de ser seleccionada por otro usuario. Elige otro horario o mesa.',
                    ]);
                }
            }

            $holdToken = 'HOLD-'.bin2hex(random_bytes(16));
            $holdDuration = (int) ($config->hold_duration_minutes ?? 10);
            $expiresAt = CarbonImmutable::now('UTC')->addMinutes($holdDuration);

            $holdId = DB::table('reservation_holds')->insertGetId([
                'hold_token' => $holdToken,
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['phone'],
                'customer_email' => $data['email'] ?? null,
                'table_ids' => json_encode($tableIds),
                'guest_count' => (int) $data['people'],
                'reservation_at' => $startUtc,
                'duration_minutes' => $durationMinutes,
                'advance_amount' => $config->advance_amount,
                'status' => 'ACTIVE',
                'expires_at' => $expiresAt,
                'notes' => $data['notes'] ?? null,
                'ip_address' => $ip,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            audit_log(desego_db(), null, 'reservations.hold_created', [
                'hold_id' => $holdId,
                'hold_token' => $holdToken,
                'table_ids' => $tableIds,
                'expires_at' => $expiresAt->toIso8601String(),
            ]);

            $tableLabels = DB::table('restaurant_tables')->whereIn('id', $tableIds)->orderBy('table_number')->pluck('label')->all();

            return [
                'ok' => true,
                'hold_token' => $holdToken,
                'advance_amount' => (float) $config->advance_amount,
                'expires_at' => $expiresAt->toIso8601String(),
                'expires_in_seconds' => $holdDuration * 60,
                'tables' => $tableLabels,
                'guest_count' => (int) $data['people'],
                'date' => $data['date'],
                'time' => $data['time'],
                'tolerance_minutes' => (int) $config->tolerance_minutes,
                'message' => "Capacidad bloqueada temporalmente por {$holdDuration} minutos para completar el anticipo.",
            ];
        }, 3);
    }

    public function confirmPayment(string $holdToken, string $provider = 'niubiz', ?string $externalReference = null, array $rawResponse = []): array
    {
        return DB::transaction(function () use ($holdToken, $provider, $externalReference, $rawResponse): array {
            // Idempotencia: Verificar si ya existe un pago confirmado con la misma referencia externa
            if ($externalReference) {
                $existingPayment = DB::table('reservation_payments')
                    ->where('provider', $provider)
                    ->where('external_reference', $externalReference)
                    ->where('status', 'PAID')
                    ->first();

                if ($existingPayment) {
                    $res = DB::table('reservations')->where('id', $existingPayment->reservation_id)->first();
                    if ($res) {
                        return [
                            'ok' => true,
                            'id' => (int) $res->id,
                            'reservation_code' => $res->reservation_code,
                            'status' => $res->status,
                            'advance_amount' => (float) $res->advance_amount,
                            'message' => 'Reserva ya confirmada previamente.',
                        ];
                    }
                }
            }

            // Buscar y bloquear el hold
            $hold = DB::table('reservation_holds')->where('hold_token', $holdToken)->lockForUpdate()->first();
            abort_unless($hold, 404, 'El bloqueo de reserva no existe o ya no es válido.');

            if ($hold->status === 'CONVERTED' && $hold->reservation_id) {
                $res = DB::table('reservations')->where('id', $hold->reservation_id)->first();

                return [
                    'ok' => true,
                    'id' => (int) $res->id,
                    'reservation_code' => $res->reservation_code,
                    'status' => $res->status,
                    'advance_amount' => (float) $res->advance_amount,
                    'message' => 'Reserva ya confirmada.',
                ];
            }

            $nowUtc = CarbonImmutable::now('UTC');
            $tableIds = json_decode((string) $hold->table_ids, true) ?: [];
            sort($tableIds);

            // Bloquear mesas determinísticamente
            DB::table('restaurant_tables')->whereIn('id', $tableIds)->lockForUpdate()->get();

            $isExpired = $hold->status === 'EXPIRED' || CarbonImmutable::parse($hold->expires_at)->lessThan($nowUtc);

            if ($isExpired) {
                // Pago tardío: Verificar si aún es posible confirmar la capacidad
                $canFulfill = true;
                $startUtc = CarbonImmutable::parse($hold->reservation_at);
                foreach ($tableIds as $tid) {
                    if ($this->overlaps($tid, $startUtc, (int) $hold->duration_minutes, null, $holdToken)) {
                        $canFulfill = false;
                        break;
                    }
                }

                if (! $canFulfill) {
                    // Sobrevendido o no disponible: Registrar incidencia y preparar devolución
                    $incidentPaymentId = DB::table('reservation_payments')->insertGetId([
                        'reservation_id' => null,
                        'amount' => $hold->advance_amount,
                        'currency' => 'PEN',
                        'provider' => $provider,
                        'external_reference' => $externalReference,
                        'status' => 'REFUNDED',
                        'paid_at' => now(),
                        'refunded_at' => now(),
                        'refund_amount' => $hold->advance_amount,
                        'refund_reason' => 'Pago tardío: bloqueo expiró y la mesa fue tomada por otra reserva.',
                        'raw_response' => json_encode($rawResponse),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    audit_log(desego_db(), null, 'reservations.late_payment_refunded', [
                        'hold_token' => $holdToken,
                        'payment_id' => $incidentPaymentId,
                        'external_reference' => $externalReference,
                    ]);

                    return [
                        'ok' => false,
                        'refunded' => true,
                        'message' => 'Tu pago ingresó después del tiempo límite de 10 minutos y la capacidad ya no está disponible. Se ha registrado la devolución de tus S/ 10.00.',
                    ];
                }
            }

            // Confirmar y crear la reserva real
            $config = $this->config();
            $tolerance = (int) ($config->tolerance_minutes ?? 15);
            $startUtc = CarbonImmutable::parse($hold->reservation_at);
            $arrivalDeadline = $startUtc->addMinutes($tolerance);

            $primaryTableId = $tableIds[0] ?? 1;
            $actorId = auth()->id()
                ?? session('user_id')
                ?? DB::table('users')->join('roles', 'roles.id', '=', 'users.role_id')
                    ->where('roles.code', 'admin')->where('users.status', 'active')->value('users.id')
                ?? DB::table('users')->where('status', 'active')->value('id')
                ?? null;

            $linkedCustomerId = session('storefront_customer_id') ?: null;
            if (! $linkedCustomerId) {
                $linkedCustomerId = DB::table('customers')
                    ->where(function ($query) use ($hold): void {
                        if (! empty($hold->customer_email)) {
                            $query->where('email', mb_strtolower((string) $hold->customer_email));
                        }
                        if (! empty($hold->customer_phone)) {
                            $query->orWhere('phone', (string) $hold->customer_phone);
                        }
                    })
                    ->orderByDesc('user_id')
                    ->value('id');
            }

            $reservationId = DB::table('reservations')->insertGetId([
                'customer_id' => $linkedCustomerId,
                'customer_name' => $hold->customer_name,
                'customer_phone' => $hold->customer_phone,
                'customer_email' => $hold->customer_email,
                'table_id' => $primaryTableId,
                'guest_count' => $hold->guest_count,
                'reservation_at' => $startUtc,
                'duration_minutes' => $hold->duration_minutes,
                'status' => 'CONFIRMED',
                'payment_status' => 'PAID',
                'advance_amount' => $hold->advance_amount,
                'applied_credit_amount' => 0.00,
                'arrival_deadline' => $arrivalDeadline,
                'policy_version' => 'v1.0',
                'hold_token' => $holdToken,
                'notes' => $hold->notes,
                'created_by' => $actorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $code = 'RES-'.str_pad((string) $reservationId, 6, '0', STR_PAD_LEFT);
            DB::table('reservations')->where('id', $reservationId)->update(['reservation_code' => $code]);

            // Asignar todas las mesas en la tabla pivote
            foreach ($tableIds as $tid) {
                DB::table('reservation_tables')->insertOrIgnore([
                    'reservation_id' => $reservationId,
                    'table_id' => $tid,
                ]);
            }

            // Registrar el pago de la reserva
            $paymentId = DB::table('reservation_payments')->insertGetId([
                'reservation_id' => $reservationId,
                'amount' => $hold->advance_amount,
                'currency' => 'PEN',
                'provider' => $provider,
                'external_reference' => $externalReference ?: 'REF-'.bin2hex(random_bytes(6)),
                'status' => 'PAID',
                'paid_at' => now(),
                'raw_response' => json_encode($rawResponse),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Marcar hold como convertido
            DB::table('reservation_holds')->where('id', $hold->id)->update([
                'status' => 'CONVERTED',
                'reservation_id' => $reservationId,
                'updated_at' => now(),
            ]);

            // Registrar auditoría
            DB::table('reservation_audit_logs')->insert([
                'reservation_id' => $reservationId,
                'user_id' => null,
                'action' => 'payment_confirmed',
                'details' => json_encode([
                    'payment_id' => $paymentId,
                    'amount' => (float) $hold->advance_amount,
                    'provider' => $provider,
                    'external_reference' => $externalReference,
                    'tables' => $tableIds,
                ]),
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);

            $assignedTables = DB::table('restaurant_tables')->whereIn('id', $tableIds)->orderBy('table_number')->get(['id', 'table_number', 'label', 'capacity'])->all();

            return [
                'ok' => true,
                'id' => $reservationId,
                'reservation_code' => $code,
                'status' => 'CONFIRMED',
                'customer_name' => $hold->customer_name,
                'guest_count' => (int) $hold->guest_count,
                'reservation_at' => $startUtc->toIso8601String(),
                'arrival_deadline' => $arrivalDeadline->toIso8601String(),
                'advance_amount' => (float) $hold->advance_amount,
                'tables' => $assignedTables,
                'message' => (float) $hold->advance_amount > 0
                    ? '¡Reserva confirmada con éxito! Tu anticipo de S/ '.number_format((float) $hold->advance_amount, 2).' será aplicado como crédito a tu consumo final.'
                    : '¡Reserva confirmada con éxito! Te esperamos en nuestra sede de Huaycán.',
            ];
        }, 3);
    }

    public function expireStaleHolds(): int
    {
        $nowUtc = CarbonImmutable::now('UTC');

        return DB::table('reservation_holds')
            ->where('status', 'ACTIVE')
            ->where('expires_at', '<=', $nowUtc)
            ->update([
                'status' => 'EXPIRED',
                'updated_at' => now(),
            ]);
    }

    public function processNoShows(): array
    {
        $nowUtc = CarbonImmutable::now('UTC');
        $affected = [];

        $reservations = DB::table('reservations')
            ->whereIn('status', ['Confirmada', 'CONFIRMED'])
            ->whereNull('checked_in_at')
            ->where('arrival_deadline', '<=', $nowUtc)
            ->get();

        foreach ($reservations as $res) {
            DB::transaction(function () use ($res, &$affected): void {
                $row = DB::table('reservations')->where('id', $res->id)->lockForUpdate()->first();
                if (! $row || ! in_array($row->status, ['Confirmada', 'CONFIRMED'], true) || $row->checked_in_at) {
                    return;
                }

                // Pasar a NO_SHOW y retener el anticipo
                DB::table('reservations')->where('id', $row->id)->update([
                    'status' => 'NO_SHOW',
                    'payment_status' => 'RETAINED',
                    'updated_at' => now(),
                ]);

                DB::table('reservation_payments')->where('reservation_id', $row->id)->update([
                    'status' => 'RETAINED',
                    'updated_at' => now(),
                ]);

                // Registrar auditoría con motivo y fecha
                DB::table('reservation_audit_logs')->insert([
                    'reservation_id' => $row->id,
                    'user_id' => null,
                    'action' => 'marked_no_show',
                    'details' => json_encode([
                        'reason' => 'Inasistencia: la hora actual superó los 15 minutos de tolerancia.',
                        'arrival_deadline' => $row->arrival_deadline,
                        'advance_amount_retained' => (float) $row->advance_amount,
                    ]),
                    'ip_address' => 'system',
                    'created_at' => now(),
                ]);

                $affected[] = $row->reservation_code;
            }, 3);
        }

        return $affected;
    }

    public function checkIn(int $reservationId, ?int $staffUserId = null): array
    {
        return DB::transaction(function () use ($reservationId, $staffUserId): array {
            $res = DB::table('reservations')->where('id', $reservationId)->lockForUpdate()->first();
            abort_unless($res, 404, 'Reserva no encontrada.');

            abort_if(in_array($res->status, ['CANCELLED', 'Cancelada'], true), 409, 'Esta reserva fue cancelada.');
            abort_if(in_array($res->status, ['CHECKED_IN', 'IN_SERVICE', 'COMPLETED', 'Sentada', 'Completada'], true), 409, 'El cliente ya registró su llegada previamente.');

            $nowUtc = CarbonImmutable::now('UTC');
            $isTardy = $res->arrival_deadline && CarbonImmutable::parse($res->arrival_deadline)->lessThan($nowUtc);

            if ($isTardy && $res->status === 'NO_SHOW') {
                abort_unless($staffUserId, 403, 'La reserva expiró por inasistencia. Requiere autorización del administrador para reactivación justificada.');
            }

            DB::table('reservations')->where('id', $res->id)->update([
                'status' => 'CHECKED_IN',
                'checked_in_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('reservation_audit_logs')->insert([
                'reservation_id' => $res->id,
                'user_id' => $staffUserId,
                'action' => 'check_in',
                'details' => json_encode([
                    'checked_in_at' => now()->toIso8601String(),
                    'was_tardy' => $isTardy,
                ]),
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);

            return [
                'ok' => true,
                'reservation_code' => $res->reservation_code,
                'status' => 'CHECKED_IN',
                'message' => 'Llegada registrada exitosamente.',
            ];
        }, 3);
    }

    public function seatAndLinkOrder(int $reservationId, string $diningSessionId, ?int $staffUserId = null): array
    {
        return DB::transaction(function () use ($reservationId, $diningSessionId, $staffUserId): array {
            $res = DB::table('reservations')->where('id', $reservationId)->lockForUpdate()->first();
            abort_unless($res, 404, 'Reserva no encontrada.');

            $visit = DB::table('dining_sessions')->where('id', $diningSessionId)->lockForUpdate()->first();
            abort_unless($visit, 404, 'Sesión de mesa no encontrada.');

            DB::table('reservations')->where('id', $res->id)->update([
                'status' => 'IN_SERVICE',
                'seated_at' => now(),
                'dining_session_id' => $diningSessionId,
                'updated_at' => now(),
            ]);

            // Marcar mesa física como ocupada
            DB::table('restaurant_tables')->where('id', $visit->table_id)->update([
                'operational_status' => 'occupied',
                'updated_at' => now(),
            ]);

            DB::table('reservation_audit_logs')->insert([
                'reservation_id' => $res->id,
                'user_id' => $staffUserId,
                'action' => 'seated_at_table',
                'details' => json_encode([
                    'dining_session_id' => $diningSessionId,
                    'table_id' => $visit->table_id,
                ]),
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);

            return [
                'ok' => true,
                'status' => 'IN_SERVICE',
                'message' => 'Cliente sentado en la mesa. La reserva quedó vinculada a la cuenta de la mesa.',
            ];
        });
    }

    public function applyReservationCreditToOrder(int $orderId, ?int $staffUserId = null): array
    {
        return DB::transaction(function () use ($orderId, $staffUserId): array {
            $order = DB::table('orders')->where('id', $orderId)->lockForUpdate()->first();
            abort_unless($order, 404, 'Pedido no encontrado.');

            if (! $order->dining_session_id) {
                return ['applied' => false, 'message' => 'El pedido no pertenece a una mesa en salón.'];
            }

            // Buscar reserva vinculada a esta sesión de mesa
            $res = DB::table('reservations')
                ->where('dining_session_id', $order->dining_session_id)
                ->where('payment_status', 'PAID')
                ->where('applied_credit_amount', '<=', 0)
                ->lockForUpdate()
                ->first();

            if (! $res) {
                return ['applied' => false, 'message' => 'No hay anticipo pendiente de aplicar para esta mesa.'];
            }

            $creditAvailable = (float) $res->advance_amount;
            $orderTotal = (float) $order->total;

            $creditToApply = min($creditAvailable, $orderTotal);
            $newTotal = round($orderTotal - $creditToApply, 2);

            // Actualizar la orden y la reserva
            DB::table('orders')->where('id', $order->id)->update([
                'discount' => round((float) $order->discount + $creditToApply, 2),
                'total' => $newTotal,
                'updated_at' => now(),
            ]);

            DB::table('reservations')->where('id', $res->id)->update([
                'applied_credit_amount' => $creditToApply,
                'order_id' => $order->id,
                'updated_at' => now(),
            ]);

            DB::table('reservation_audit_logs')->insert([
                'reservation_id' => $res->id,
                'user_id' => $staffUserId,
                'action' => 'credit_applied',
                'details' => json_encode([
                    'order_id' => $order->id,
                    'applied_amount' => $creditToApply,
                    'previous_order_total' => $orderTotal,
                    'new_order_total' => $newTotal,
                ]),
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);

            return [
                'applied' => true,
                'credit_applied' => $creditToApply,
                'new_order_total' => $newTotal,
                'remaining_credit' => round($creditAvailable - $creditToApply, 2),
                'message' => "Se aplicó el crédito de anticipo de S/ {$creditToApply} al pedido {$order->order_number}.",
            ];
        });
    }

    public function cancelReservation(int $reservationId, string $reason, bool $isStaff = false, ?int $staffUserId = null): array
    {
        return DB::transaction(function () use ($reservationId, $reason, $isStaff, $staffUserId): array {
            $res = DB::table('reservations')->where('id', $reservationId)->lockForUpdate()->first();
            abort_unless($res, 404, 'Reserva no encontrada.');

            abort_if(in_array($res->status, ['COMPLETED', 'Completada', 'CANCELLED', 'Cancelada'], true), 409, 'La reserva ya no puede ser cancelada en su estado actual.');

            $config = $this->config();
            $nowUtc = CarbonImmutable::now('UTC');
            $startUtc = CarbonImmutable::parse($res->reservation_at);
            $deadlineHours = (int) ($config->cancellation_deadline_hours ?? 2);
            $refundableUntil = $startUtc->subHours($deadlineHours);

            $shouldRefund = $isStaff || $nowUtc->lessThanOrEqualTo($refundableUntil);

            DB::table('reservations')->where('id', $res->id)->update([
                'status' => 'CANCELLED',
                'payment_status' => $shouldRefund ? 'REFUNDED' : 'RETAINED',
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'updated_at' => now(),
            ]);

            if ($shouldRefund && (float) $res->advance_amount > 0) {
                DB::table('reservation_payments')->where('reservation_id', $res->id)->update([
                    'status' => 'REFUNDED',
                    'refunded_at' => now(),
                    'refund_amount' => $res->advance_amount,
                    'refund_reason' => $reason,
                    'refunded_by' => $staffUserId,
                    'updated_at' => now(),
                ]);
            }

            DB::table('reservation_audit_logs')->insert([
                'reservation_id' => $res->id,
                'user_id' => $staffUserId,
                'action' => 'cancelled',
                'details' => json_encode([
                    'reason' => $reason,
                    'refunded' => $shouldRefund,
                    'refund_amount' => $shouldRefund ? (float) $res->advance_amount : 0,
                ]),
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);

            return [
                'ok' => true,
                'refunded' => $shouldRefund,
                'message' => $shouldRefund
                    ? ((float) $res->advance_amount > 0
                        ? 'Reserva cancelada correctamente. Se procesó la devolución de tu anticipo de S/ '.number_format((float) $res->advance_amount, 2).'.'
                        : 'Reserva cancelada correctamente.')
                    : 'Reserva cancelada. Según la política aceptada, las cancelaciones tardías no procesan devolución automática, pero puedes solicitar revisión en recepción.',
            ];
        });
    }
}
