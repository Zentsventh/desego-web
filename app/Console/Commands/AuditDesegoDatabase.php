<?php

namespace App\Console\Commands;

use App\Support\Desego\DatabaseIntegrityAudit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class AuditDesegoDatabase extends Command
{
    protected $signature = 'desego:audit {--report= : Guardar el informe JSON en esta ruta}';

    protected $description = 'Audita todas las tablas, claves, relaciones, indices, secuencias y registros huerfanos';

    public function handle(DatabaseIntegrityAudit $audit): int
    {
        $pdo = DB::connection()->getPdo();
        try {
            $pdo->beginTransaction();
            $report = $audit->inspect($pdo);
            $pdo->rollBack();
            if ($path = $this->option('report')) {
                if (file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), LOCK_EX) === false) {
                    $this->error('No se pudo guardar el informe.');

                    return self::FAILURE;
                }
            }
            $this->info('Base auditada: '.$report['database']);
            $this->table(['Comprobacion', 'Total'], array_map(static fn ($name, $value) => [$name, $value], array_keys($report['summary']), $report['summary']));
            foreach ($report['issues'] as $issue) {
                $this->error($issue);
            }
            if (! $report['healthy']) {
                return self::FAILURE;
            }
            $this->info('Integridad correcta. Sin registros huerfanos ni claves o indices faltantes.');

            return self::SUCCESS;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->error($error->getMessage());

            return self::FAILURE;
        }
    }
}
