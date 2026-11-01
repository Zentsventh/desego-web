<?php

namespace App\Console\Commands;

use App\Services\ReservationAvailabilityService;
use Illuminate\Console\Command;

class ProcessReservationExpirations extends Command
{
    protected $signature = 'reservations:process-expirations';

    protected $description = 'Expira bloqueos de pago vencidos y procesa inasistencias tras la tolerancia de 15 minutos';

    public function handle(ReservationAvailabilityService $service): int
    {
        $expiredHolds = $service->expireStaleHolds();
        if ($expiredHolds > 0) {
            $this->info("Se expiraron {$expiredHolds} bloqueos temporales vencidos.");
        }

        $noShows = $service->processNoShows();
        if (! empty($noShows)) {
            $codes = implode(', ', $noShows);
            $this->warn("Reservas marcadas como NO_SHOW por superar la tolerancia: {$codes}");
        }

        if ($expiredHolds === 0 && empty($noShows)) {
            $this->line('No hay reservas ni bloqueos vencidos en este ciclo.');
        }

        return self::SUCCESS;
    }
}
