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
        $this->validateRelations();

        Schema::create('reservation_hold_tables', function (Blueprint $table): void {
            $table->unsignedBigInteger('hold_id');
            $table->unsignedInteger('table_id');
            $table->primary(['hold_id', 'table_id']);
            $table->foreign('hold_id')->references('id')->on('reservation_holds')->cascadeOnDelete();
            $table->foreign('table_id')->references('id')->on('restaurant_tables');
        });
        Schema::create('dining_payment_attempt_orders', function (Blueprint $table): void {
            $table->char('attempt_id', 36);
            $table->unsignedBigInteger('order_id');
            $table->primary(['attempt_id', 'order_id']);
            $table->foreign('attempt_id')->references('id')->on('dining_payment_attempts')->cascadeOnDelete();
            $table->foreign('order_id')->references('id')->on('orders');
        });
        Schema::create('order_item_options', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_item_id');
            $table->unsignedBigInteger('option_id')->nullable();
            $table->unsignedSmallInteger('position');
            $table->string('group_name', 100);
            $table->string('name', 100);
            $table->decimal('price_delta', 10, 2);
            $table->unique(['order_item_id', 'position']);
            $table->foreign('order_item_id')->references('id')->on('order_items')->cascadeOnDelete();
            $table->foreign('option_id')->references('id')->on('product_options')->nullOnDelete();
        });

        DB::transaction(function (): void {
            foreach (DB::table('reservation_holds')->orderBy('id')->cursor() as $hold) {
                foreach ($this->identifiers($hold->table_ids) as $tableId) {
                    DB::table('reservation_hold_tables')->insert(['hold_id' => $hold->id, 'table_id' => $tableId]);
                }
            }
            foreach (DB::table('dining_payment_attempts')->orderBy('id')->cursor() as $attempt) {
                foreach ($this->identifiers($attempt->order_ids) as $orderId) {
                    DB::table('dining_payment_attempt_orders')->insert(['attempt_id' => $attempt->id, 'order_id' => $orderId]);
                }
            }
            foreach (DB::table('order_items')->whereNotNull('selected_options')->orderBy('id')->cursor() as $item) {
                foreach (json_decode($item->selected_options, true, 512, JSON_THROW_ON_ERROR) as $position => $option) {
                    $optionId = $option['id'] ?? null;
                    DB::table('order_item_options')->insert([
                        'order_item_id' => $item->id,
                        'option_id' => $optionId && DB::table('product_options')->where('id', $optionId)->exists() ? $optionId : null,
                        'position' => $position,
                        'group_name' => $option['group_name'],
                        'name' => $option['name'],
                        'price_delta' => $option['price_delta'],
                    ]);
                }
            }
        });

        Schema::table('reservation_holds', fn (Blueprint $table) => $table->dropColumn('table_ids'));
        Schema::table('dining_payment_attempts', function (Blueprint $table): void {
            $table->dropColumn('order_ids');
            $table->dateTime('expires_at')->change();
        });
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('selected_options'));
    }

    /** @return list<int> */
    private function identifiers(string $json): array
    {
        $values = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($values) || ! array_is_list($values) || $values === []) {
            throw new RuntimeException('La relación JSON debe contener una lista no vacía de identificadores.');
        }
        foreach ($values as $value) {
            if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 1) {
                throw new RuntimeException('Identificador inválido en una relación JSON.');
            }
        }

        return array_values(array_unique(array_map('intval', $values)));
    }

    private function validateRelations(): void
    {
        foreach (DB::table('reservation_holds')->orderBy('id')->cursor() as $hold) {
            $ids = $this->identifiers($hold->table_ids);
            if (DB::table('restaurant_tables')->whereIn('id', $ids)->count() !== count($ids)) {
                throw new RuntimeException("El bloqueo {$hold->id} contiene mesas inexistentes. Corrige los datos antes de migrar.");
            }
        }
        foreach (DB::table('dining_payment_attempts')->orderBy('id')->cursor() as $attempt) {
            $ids = $this->identifiers($attempt->order_ids);
            if (DB::table('orders')->whereIn('id', $ids)->where('dining_session_id', $attempt->dining_session_id)->count() !== count($ids)) {
                throw new RuntimeException("El intento {$attempt->id} contiene pedidos inexistentes o de otra sesión.");
            }
        }
        foreach (DB::table('order_items')->whereNotNull('selected_options')->orderBy('id')->cursor() as $item) {
            $options = json_decode($item->selected_options, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($options) || ! array_is_list($options)) {
                throw new RuntimeException("Las opciones del detalle {$item->id} no son una lista.");
            }
            foreach ($options as $option) {
                if (! is_array($option) || ! isset($option['group_name'], $option['name'], $option['price_delta']) || ! is_numeric($option['price_delta'])) {
                    throw new RuntimeException("Las opciones históricas del detalle {$item->id} están incompletas.");
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservation_holds', fn (Blueprint $table) => $table->json('table_ids')->nullable());
        Schema::table('dining_payment_attempts', fn (Blueprint $table) => $table->json('order_ids')->nullable());
        Schema::table('order_items', fn (Blueprint $table) => $table->json('selected_options')->nullable());

        foreach (DB::table('reservation_holds')->orderBy('id')->cursor() as $hold) {
            DB::table('reservation_holds')->where('id', $hold->id)->update(['table_ids' => json_encode(DB::table('reservation_hold_tables')->where('hold_id', $hold->id)->orderBy('table_id')->pluck('table_id')->all(), JSON_THROW_ON_ERROR)]);
        }
        foreach (DB::table('dining_payment_attempts')->orderBy('id')->cursor() as $attempt) {
            DB::table('dining_payment_attempts')->where('id', $attempt->id)->update(['order_ids' => json_encode(DB::table('dining_payment_attempt_orders')->where('attempt_id', $attempt->id)->orderBy('order_id')->pluck('order_id')->all(), JSON_THROW_ON_ERROR)]);
        }
        foreach (DB::table('order_items')->orderBy('id')->cursor() as $item) {
            $options = DB::table('order_item_options')->where('order_item_id', $item->id)->orderBy('position')->get(['option_id as id', 'group_name', 'name', 'price_delta']);
            if ($options->isNotEmpty()) {
                DB::table('order_items')->where('id', $item->id)->update(['selected_options' => $options->toJson()]);
            }
        }
        Schema::table('reservation_holds', fn (Blueprint $table) => $table->json('table_ids')->nullable(false)->change());
        Schema::table('dining_payment_attempts', fn (Blueprint $table) => $table->json('order_ids')->nullable(false)->change());
        Schema::dropIfExists('order_item_options');
        Schema::dropIfExists('dining_payment_attempt_orders');
        Schema::dropIfExists('reservation_hold_tables');
    }
};
