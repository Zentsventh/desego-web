<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneDesegoApiLimits extends Command
{
    protected $signature = 'desego:prune-api-limits';

    protected $description = 'Elimina ventanas de limite API de mas de siete dias';

    public function handle(): int
    {
        $count = DB::delete('DELETE FROM api_rate_limit_windows WHERE window_start < (UTC_TIMESTAMP() - INTERVAL 7 DAY)');
        $this->info('Ventanas eliminadas: '.$count);

        return self::SUCCESS;
    }
}
