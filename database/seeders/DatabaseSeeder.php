<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\DiningService;
use App\Services\ReservationAvailabilityService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) config('desego.seeding.admin_email');
        $password = (string) config('desego.seeding.admin_password');
        $hasAdministrator = DB::table('users')->join('roles', 'roles.id', '=', 'users.role_id')
            ->where('roles.code', 'admin')->where('users.status', 'active')->exists();
        if (! $hasAdministrator && strlen($password) < 12) {
            throw new RuntimeException('Configura DESEGO_ADMIN_PASSWORD con al menos 12 caracteres antes de inicializar el administrador.');
        }
        if (! $hasAdministrator && DB::table('users')->where('email', $email)->exists()) {
            throw new RuntimeException('DESEGO_ADMIN_EMAIL pertenece a una cuenta existente sin acceso administrativo. Elige otro correo.');
        }

        DB::transaction(function () use ($email, $password, $hasAdministrator): void {
            $this->call(RestaurantReferenceSeeder::class);
            $this->call(OfficialCatalogSeeder::class);
            if (! $hasAdministrator) {
                User::create([
                    'name' => 'Administrador General',
                    'email' => $email,
                    'password_hash' => Hash::make($password),
                    'role_id' => DB::table('roles')->where('code', 'admin')->value('id'),
                    'status' => 'active',
                ]);
                $this->command?->info('Administrador inicial creado con la contraseña configurada.');
            }
            app(DiningService::class)->prepare();
            app(ReservationAvailabilityService::class)->config();
        });
    }
}
