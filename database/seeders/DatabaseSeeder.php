<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\DiningService;
use App\Support\Desego\SqlDump;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Semilla maestra para inicializar una base de datos limpia de desarrollo/staging.
     */
    public function run(): void
    {
        $pdo = DB::connection()->getPdo();
        $referenceDataPath = database_path('desego/reference-data.sql');

        // 1. Cargar el catálogo base y roles si aún no existen
        $hasRoles = DB::table('roles')->exists();
        if (! $hasRoles && is_file($referenceDataPath)) {
            $this->command?->info('Sembrando catálogo base (roles, permisos, categorías, productos)...');
            $parser = app(SqlDump::class);
            $statements = $parser->statements((string) file_get_contents($referenceDataPath));

            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            try {
                $pdo->beginTransaction();
                foreach ($statements as $statement) {
                    $pdo->exec($statement);
                }
                $pdo->commit();
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            } finally {
                $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            }
        }

        // 2. Crear usuario Administrador inicial si no existe
        $adminEmail = env('DESEGO_ADMIN_EMAIL', 'admin@desego.pe');
        $adminPassword = env('DESEGO_ADMIN_PASSWORD', 'admin12345');

        $adminExists = DB::table('users')->where('email', $adminEmail)->exists();
        if (! $adminExists) {
            $adminRoleId = DB::table('roles')->where('code', 'admin')->value('id') ?? 1;

            User::create([
                'name' => 'Administrador General',
                'email' => $adminEmail,
                'password_hash' => Hash::make($adminPassword),
                'role_id' => $adminRoleId,
                'status' => 'active',
            ]);

            $this->command?->info("Usuario Administrador creado: {$adminEmail} / {$adminPassword}");
        }

        // 3. Preparar mesas y códigos QR del salón
        if (class_exists(DiningService::class)) {
            app(DiningService::class)->prepare();
            $this->command?->info('Mesas del salón y tokens QR inicializados.');
        }

        // 4. Sembrar catálogo oficial completo de DeSego (35 productos en 4 categorías, horarios y zonas)
        $this->call(OfficialCatalogSeeder::class);
        $this->command?->info('Catálogo oficial de DeSego Cevichería inicializado con éxito (35 platos).');
    }
}
