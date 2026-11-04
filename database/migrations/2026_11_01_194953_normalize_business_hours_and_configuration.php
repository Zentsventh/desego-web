<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (DB::table('reservation_configurations')->whereNotNull('online_table_ids')->orderBy('id')->get() as $config) {
            $ids = json_decode($config->online_table_ids, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($ids) || ! array_is_list($ids) || DB::table('restaurant_tables')->whereIn('id', $ids)->count() !== count(array_unique($ids))) {
                throw new RuntimeException('La configuración de mesas en línea contiene identificadores inválidos.');
            }
            if (DB::table('reservation_configurations')->whereNotNull('online_table_ids')->count() > 1) {
                throw new RuntimeException('Hay varias configuraciones de mesas en línea. Unifica su alcance antes de migrar.');
            }
            DB::table('restaurant_tables')->whereNotIn('id', $ids)->where('is_online_reservable', true)->update(['is_online_reservable' => false]);
            DB::table('restaurant_tables')->whereIn('id', $ids)->where('is_online_reservable', false)->update(['is_online_reservable' => true]);
        }
        Schema::table('reservation_configurations', fn (Blueprint $table) => $table->dropColumn('online_table_ids'));
        DB::statement("ALTER TABLE business_hours DROP COLUMN day_name, ADD COLUMN day_name VARCHAR(20) GENERATED ALWAYS AS (CASE day_of_week WHEN 1 THEN 'Lunes' WHEN 2 THEN 'Martes' WHEN 3 THEN CONCAT('Mi',CHAR(0xC3A9 USING utf8mb4),'rcoles') WHEN 4 THEN 'Jueves' WHEN 5 THEN 'Viernes' WHEN 6 THEN CONCAT('S',CHAR(0xC3A1 USING utf8mb4),'bado') WHEN 7 THEN 'Domingo' END) VIRTUAL AFTER day_of_week");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE business_hours DROP COLUMN day_name, ADD COLUMN day_name VARCHAR(20) NOT NULL DEFAULT '' AFTER day_of_week");
        DB::statement("UPDATE business_hours SET day_name = CASE day_of_week WHEN 1 THEN 'Lunes' WHEN 2 THEN 'Martes' WHEN 3 THEN 'Miércoles' WHEN 4 THEN 'Jueves' WHEN 5 THEN 'Viernes' WHEN 6 THEN 'Sábado' WHEN 7 THEN 'Domingo' END");
        Schema::table('reservation_configurations', fn (Blueprint $table) => $table->json('online_table_ids')->nullable());
        $ids = DB::table('restaurant_tables')->where('is_online_reservable', true)->orderBy('id')->pluck('id')->all();
        DB::table('reservation_configurations')->update(['online_table_ids' => json_encode($ids, JSON_THROW_ON_ERROR)]);
    }
};
