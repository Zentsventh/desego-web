<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ReservationAvailability
{
    private ReservationAvailabilityService $service;

    public function __construct(?ReservationAvailabilityService $service = null)
    {
        $this->service = $service ?? app(ReservationAvailabilityService::class);
    }

    public function schedule(string $date): array
    {
        return $this->service->schedule($date);
    }

    public function start(string $date, string $time): CarbonImmutable
    {
        return $this->service->start($date, $time);
    }

    public function overlaps(int $tableId, CarbonImmutable $start, int $duration = 120, ?int $exceptId = null): bool
    {
        return $this->service->overlaps($tableId, $start, $duration, $exceptId);
    }

    public function unavailable(object $table, CarbonImmutable $start): bool
    {
        return $this->service->unavailable($table, $start, 90);
    }

    public function availability(string $date, ?string $time): array
    {
        return $this->service->availability($date, $time);
    }

    public function tables(int $reservationId): array
    {
        $primary = DB::table('reservations')->where('id', $reservationId)->value('table_id');

        return DB::table('restaurant_tables as t')->leftJoin('reservation_table_settings as s', 's.table_id', '=', 't.id')
            ->where(function ($query) use ($primary, $reservationId): void {
                $query->where('t.id', $primary)->orWhereIn('t.id', DB::table('reservation_tables')->where('reservation_id', $reservationId)->select('table_id'));
            })->orderBy('t.table_number')->get(['t.id', 't.label', 't.zone', 't.capacity', 's.join_group'])->map(fn ($table): array => (array) $table)->all();
    }

    public function seating(array $tables): string
    {
        if (count($tables) === 1) {
            return 'Una sola mesa';
        }
        $groups = array_unique(array_column($tables, 'join_group'));

        return count($groups) === 1 && ! empty($groups[0]) && count(array_unique(array_column($tables, 'zone'))) === 1 ? 'Mesas que se pueden unir' : 'Mesas separadas';
    }
}
