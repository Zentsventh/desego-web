<?php

namespace App\Console\Commands;

use App\Support\Desego\DatabaseIntegrityAudit;
use App\Support\Desego\DatabaseSnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ExportDesegoDatabase extends Command
{
    protected $signature = 'desego:export {--path= : Archivo SQL privado de salida}';

    protected $description = 'Exporta todas las tablas, datos y restricciones desde una instantanea consistente';

    public function handle(DatabaseSnapshot $snapshot, DatabaseIntegrityAudit $audit): int
    {
        $stream = null;
        $path = $this->option('path') ?: storage_path('app/private/desego/export-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4)).'.sql');
        try {
            $pdo = DB::connection()->getPdo();
            $report = $audit->inspect($pdo);
            if (! $report['healthy']) {
                throw new RuntimeException('Corrige la auditoria antes de exportar: '.implode('; ', $report['issues']));
            }
            if (! is_dir(dirname($path)) && ! mkdir(dirname($path), 0700, true) && ! is_dir(dirname($path))) {
                throw new RuntimeException('No se pudo crear el directorio de salida.');
            }
            $stream = @fopen($path, 'x+b');
            if ($stream === false) {
                throw new RuntimeException('El archivo ya existe o no se puede crear. Elige otra ruta privada.');
            }
            chmod($path, 0600);
            $result = $snapshot->write($pdo, $stream);
            if (! fflush($stream)) {
                throw new RuntimeException('No se pudo completar la escritura del respaldo.');
            }
            fclose($stream);
            $stream = null;
            $this->info("Exportacion completa: {$result['tables']} tablas, {$result['rows']} filas. Archivo: {$path}");

            return self::SUCCESS;
        } catch (Throwable $error) {
            if (is_resource($stream)) {
                fclose($stream);
                unlink($path);
            }
            $this->error($error->getMessage());

            return self::FAILURE;
        }
    }
}
