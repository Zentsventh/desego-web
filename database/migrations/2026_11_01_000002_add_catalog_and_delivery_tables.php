<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Ampliar tabla de productos con código comercial, banderas de catálogo y combos/dúos
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table): void {
                if (! Schema::hasColumn('products', 'code')) {
                    $table->string('code', 30)->nullable()->unique()->after('id');
                }
                if (! Schema::hasColumn('products', 'is_featured')) {
                    $table->boolean('is_featured')->default(false)->after('status');
                }
                if (! Schema::hasColumn('products', 'is_popular')) {
                    $table->boolean('is_popular')->default(false)->after('is_featured');
                }
                if (! Schema::hasColumn('products', 'is_new')) {
                    $table->boolean('is_new')->default(false)->after('is_popular');
                }
                if (! Schema::hasColumn('products', 'included_items')) {
                    $table->text('included_items')->nullable()->after('is_new'); // Para dúos, tríos y rondas
                }
                if (! Schema::hasColumn('products', 'image_url')) {
                    $table->string('image_url', 255)->nullable()->after('included_items');
                }
            });
        }

        // 2. Tabla de horarios de atención configurables desde panel
        if (! Schema::hasTable('business_hours')) {
            Schema::create('business_hours', function (Blueprint $table): void {
                $table->id();
                $table->unsignedTinyInteger('day_of_week'); // 1 = Lunes, ..., 7 = Domingo
                $table->string('day_name', 20); // Lunes, Martes, etc.
                $table->time('open_time')->default('09:00:00');
                $table->time('close_time')->default('17:00:00');
                $table->boolean('is_closed')->default(false);
                $table->timestamps();
            });
        }

        // 3. Tabla de zonas de cobertura de delivery
        if (! Schema::hasTable('delivery_zones')) {
            Schema::create('delivery_zones', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 100);
                $table->string('district', 100)->default('Ate');
                $table->string('zone_sector', 100)->nullable(); // Ej: Zona A, Zona B, etc.
                $table->decimal('center_latitude', 10, 8)->default(-12.01250000);
                $table->decimal('center_longitude', 11, 8)->default(-76.82850000);
                $table->decimal('radius_km', 6, 2)->default(5.00);
                $table->decimal('delivery_fee', 10, 2)->default(5.00);
                $table->decimal('min_order_amount', 10, 2)->default(20.00);
                $table->unsignedSmallInteger('estimated_minutes_min')->default(30);
                $table->unsignedSmallInteger('estimated_minutes_max')->default(50);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_zones');
        Schema::dropIfExists('business_hours');

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table): void {
                $columns = ['code', 'is_featured', 'is_popular', 'is_new', 'included_items', 'image_url'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('products', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
