<?php

namespace App\Console\Commands;

use App\Services\DiningService;
use Illuminate\Console\Command;

class PrepareDiningRoom extends Command
{
    protected $signature = 'dining:prepare';

    protected $description = 'Prepara las doce mesas y sus identificadores QR sin borrar datos existentes';

    public function handle(DiningService $dining): int
    {
        $dining->prepare();
        $this->info('Las doce mesas están preparadas. Imprime los QR desde el salón del panel.');

        return self::SUCCESS;
    }
}
