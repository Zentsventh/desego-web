<?php

namespace Database\Seeders;

use App\Support\Desego\RestaurantReferenceData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RestaurantReferenceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $existingRoles = DB::table('roles')->pluck('code')->all();
            $keys = [
                'roles' => ['code'], 'permissions' => ['code'], 'allergens' => ['name'],
                'integration_registry' => ['integration_code'], 'cash_register_mutex' => ['id'],
                'invoice_sequences' => ['document_type', 'series'], 'loyalty_settings' => ['id'],
                'system_settings' => ['setting_key'],
            ];
            foreach (RestaurantReferenceData::TABLES as $table => $rows) {
                foreach ($rows as $row) {
                    $identity = array_intersect_key($row, array_flip($keys[$table]));
                    if (! DB::table($table)->where($identity)->exists()) {
                        DB::table($table)->insert($row);
                    }
                }
            }
            $roles = DB::table('roles')->pluck('id', 'code');
            $permissions = DB::table('permissions')->pluck('id', 'code');
            foreach (RestaurantReferenceData::ROLE_PERMISSIONS as $role => $codes) {
                if (in_array($role, $existingRoles, true)) {
                    continue;
                }
                foreach ($codes as $code) {
                    DB::table('role_permissions')->insertOrIgnore(['role_id' => $roles[$role], 'permission_id' => $permissions[$code]]);
                }
            }
            DB::table('roles')->insertOrIgnore(['code' => 'customer', 'name' => 'Cliente', 'description' => 'Cuenta de cliente del restaurante.', 'is_system' => true]);
        });
    }
}
