<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Throwable;

use function App\Support\Desego\desego_apply_retention;
use function App\Support\Desego\desego_backup_create;
use function App\Support\Desego\desego_db;

class BackupDesego extends Command
{
    protected $signature = 'desego:backup {--automatic : Evitar duplicar una copia automatica reciente} {--retention : Aplicar la retencion configurada}';

    protected $description = 'Crea una copia cifrada compatible con los backups DESEGO existentes';

    public function handle(): int
    {
        try {
            $pdo = desego_db();
            $last = $this->option('automatic') ? $pdo->query("SELECT created_at FROM backup_history WHERE status='completed' AND backup_kind='automatic' ORDER BY created_at DESC LIMIT 1")->fetchColumn() : false;
            if (! $last || strtotime($last.' UTC') <= time() - 20 * 3600) {
                $backup = desego_backup_create($pdo, null, $this->option('automatic') ? 'automatic' : 'manual');
                $this->info('Copia creada: '.$backup['file_name']);
            } else {
                $this->info('Ya existe una copia automatica reciente.');
            }
            if ($this->option('retention')) {
                $this->line(json_encode(desego_apply_retention($pdo, null)));
            }

            return self::SUCCESS;
        } catch (Throwable $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }
    }
}
