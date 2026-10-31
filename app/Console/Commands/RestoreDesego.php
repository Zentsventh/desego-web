<?php

namespace App\Console\Commands;

use App\Support\Desego\SqlDump;
use Illuminate\Console\Command;
use Throwable;

use function App\Support\Desego\audit_log;
use function App\Support\Desego\desego_backup_config;
use function App\Support\Desego\desego_backup_create;
use function App\Support\Desego\desego_backup_decrypt;
use function App\Support\Desego\desego_backup_verify;
use function App\Support\Desego\desego_db;

class RestoreDesego extends Command
{
    protected $signature = 'desego:restore {backup : ID de backup} {--confirm= : Debe ser RESTORE-DESEGO}';

    protected $description = 'Restaura un backup cifrado y crea antes una copia de seguridad';

    public function handle(): int
    {
        $backupId = filter_var($this->argument('backup'), FILTER_VALIDATE_INT);
        if (! $backupId || $this->option('confirm') !== 'RESTORE-DESEGO') {
            $this->error('Indica un ID valido y --confirm=RESTORE-DESEGO. La restauracion reemplaza el estado actual.');

            return self::FAILURE;
        }
        try {
            $pdo = desego_db();
            $verified = desego_backup_verify($pdo, (int) $backupId);
            $sourceSql = desego_backup_decrypt((string) file_get_contents($verified['path']), (string) desego_backup_config()['key']);
            // Before the destructive restore, create a safety copy. Its index is reinserted after restoration.
            $safety = desego_backup_create($pdo, null, 'pre_restore');
            $safetyLookup = $pdo->prepare('SELECT secondary_file_name FROM backup_history WHERE id = :id LIMIT 1');
            $safetyLookup->execute(['id' => $safety['id']]);
            $safetySecondary = $safetyLookup->fetchColumn();
            $count = 0;
            foreach (app(SqlDump::class)->statements($sourceSql) as $statement) {
                $pdo->exec($statement);
                $count++;
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            // The restore replaced backup_history with its historical snapshot; register the pre-restore file again.
            $insert = $pdo->prepare("INSERT INTO backup_history (file_name,secondary_file_name,backup_kind,status,size_bytes,sha256_hash,is_encrypted,verification_status,verified_at,created_by,created_at,error_message) VALUES (:file_name,:secondary_file_name,'pre_restore','completed',:size_bytes,:sha256_hash,1,'valid',UTC_TIMESTAMP(),NULL,UTC_TIMESTAMP(),'Copia de seguridad automática antes de restaurar otro punto de recuperación.')");
            $insert->execute(['file_name' => $safety['file_name'], 'secondary_file_name' => $safetySecondary ?: null, 'size_bytes' => $safety['size_bytes'], 'sha256_hash' => $safety['sha256_hash']]);
            audit_log($pdo, null, 'backup.restored', ['restored_backup_id' => (int) $backupId, 'safety_file' => $safety['file_name'], 'statements_executed' => $count]);
            $this->output->write("Restauración completada desde backup ID {$backupId}. Sentencias ejecutadas: {$count}.\n");
            $this->output->write("Se creó una copia de seguridad previa: {$safety['file_name']} (registrada nuevamente en backup_history).\n");
            $this->output->write("Reinicia las sesiones de usuarios y prueba inicio de sesión, pedidos, caja e inventario.\n");
        } catch (Throwable $e) {
            try {
                $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            } catch (Throwable $ignored) {
            }
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
