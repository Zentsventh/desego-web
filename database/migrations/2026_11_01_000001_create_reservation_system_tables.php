<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Configuración de reservas
        if (! Schema::hasTable('reservation_configurations')) {
            Schema::create('reservation_configurations', function (Blueprint $table): void {
                $table->increments('id');
                $table->decimal('advance_amount', 12, 2)->default(10.00);
                $table->unsignedSmallInteger('tolerance_minutes')->default(15);
                $table->unsignedSmallInteger('hold_duration_minutes')->default(10);
                $table->unsignedSmallInteger('cleaning_minutes')->default(15);
                $table->unsignedTinyInteger('max_active_reservations_per_phone')->default(2);
                $table->unsignedSmallInteger('max_advance_days')->default(30);
                $table->unsignedSmallInteger('min_advance_minutes')->default(30);
                $table->boolean('reservations_enabled')->default(true);
                $table->unsignedSmallInteger('cancellation_deadline_hours')->default(2);
                $table->json('duration_rules')->nullable();
                $table->json('online_table_ids')->nullable();
                $table->timestamps();
            });

            // Sembrar configuración inicial
            DB::table('reservation_configurations')->insert([
                'advance_amount' => 10.00,
                'tolerance_minutes' => 15,
                'hold_duration_minutes' => 10,
                'cleaning_minutes' => 15,
                'max_active_reservations_per_phone' => 2,
                'max_advance_days' => 30,
                'min_advance_minutes' => 30,
                'reservations_enabled' => true,
                'cancellation_deadline_hours' => 2,
                'duration_rules' => json_encode([
                    '1-2' => 60,
                    '3-4' => 90,
                    '5-6' => 120,
                    '7+' => 150,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 2. Modificación de restaurant_tables
        Schema::table('restaurant_tables', function (Blueprint $table): void {
            if (! Schema::hasColumn('restaurant_tables', 'is_online_reservable')) {
                $table->boolean('is_online_reservable')->default(true)->after('is_active');
            }
        });

        // 3. Extender tabla reservations
        Schema::table('reservations', function (Blueprint $table): void {
            if (! Schema::hasColumn('reservations', 'advance_amount')) {
                $table->decimal('advance_amount', 12, 2)->default(10.00)->after('notes');
            }
            if (! Schema::hasColumn('reservations', 'applied_credit_amount')) {
                $table->decimal('applied_credit_amount', 12, 2)->default(0.00)->after('advance_amount');
            }
            if (! Schema::hasColumn('reservations', 'payment_status')) {
                $table->string('payment_status', 30)->default('PENDING')->after('applied_credit_amount');
            }
            if (! Schema::hasColumn('reservations', 'payment_expires_at')) {
                $table->dateTime('payment_expires_at')->nullable()->after('payment_status');
            }
            if (! Schema::hasColumn('reservations', 'arrival_deadline')) {
                $table->dateTime('arrival_deadline')->nullable()->after('payment_expires_at');
            }
            if (! Schema::hasColumn('reservations', 'checked_in_at')) {
                $table->dateTime('checked_in_at')->nullable()->after('arrival_deadline');
            }
            if (! Schema::hasColumn('reservations', 'seated_at')) {
                $table->dateTime('seated_at')->nullable()->after('checked_in_at');
            }
            if (! Schema::hasColumn('reservations', 'cancelled_at')) {
                $table->dateTime('cancelled_at')->nullable()->after('seated_at');
            }
            if (! Schema::hasColumn('reservations', 'cancellation_reason')) {
                $table->string('cancellation_reason', 500)->nullable()->after('cancelled_at');
            }
            if (! Schema::hasColumn('reservations', 'policy_version')) {
                $table->string('policy_version', 20)->default('v1.0')->after('cancellation_reason');
            }
            if (! Schema::hasColumn('reservations', 'dining_session_id')) {
                $table->uuid('dining_session_id')->nullable()->after('policy_version');
            }
            if (! Schema::hasColumn('reservations', 'order_id')) {
                $table->unsignedBigInteger('order_id')->nullable()->after('dining_session_id');
            }
            if (! Schema::hasColumn('reservations', 'hold_token')) {
                $table->string('hold_token', 64)->nullable()->after('order_id');
            }
        });

        try {
            DB::statement("ALTER TABLE reservations MODIFY COLUMN status VARCHAR(32) NOT NULL DEFAULT 'Confirmada'");
            DB::statement('ALTER TABLE reservations MODIFY COLUMN created_by BIGINT(20) UNSIGNED NULL');
        } catch (Throwable) {
            // Ignore in environments where statement is not supported
        }

        // 4. Tabla de Bloqueos Temporales de Mesa (reservation_holds)
        if (! Schema::hasTable('reservation_holds')) {
            Schema::create('reservation_holds', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->string('hold_token', 64)->unique();
                $table->string('customer_name', 100);
                $table->string('customer_phone', 30);
                $table->string('customer_email', 190)->nullable();
                $table->json('table_ids');
                $table->unsignedTinyInteger('guest_count');
                $table->dateTime('reservation_at');
                $table->unsignedSmallInteger('duration_minutes')->default(90);
                $table->decimal('advance_amount', 12, 2)->default(10.00);
                $table->string('status', 20)->default('ACTIVE'); // ACTIVE, CONVERTED, EXPIRED, RELEASED
                $table->dateTime('expires_at');
                $table->unsignedBigInteger('reservation_id')->nullable();
                $table->string('notes', 500)->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamps();

                $table->index(['status', 'expires_at'], 'idx_holds_status_expires');
                $table->index(['reservation_at', 'status'], 'idx_holds_time_status');
            });
        }

        // 5. Tabla de Pagos de Reserva (reservation_payments)
        if (! Schema::hasTable('reservation_payments')) {
            Schema::create('reservation_payments', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('reservation_id')->nullable();
                $table->decimal('amount', 12, 2);
                $table->string('currency', 3)->default('PEN');
                $table->string('provider', 40)->default('niubiz'); // niubiz, direct, yape, cash, etc.
                $table->string('external_reference', 100)->nullable()->index();
                $table->string('status', 30)->default('PENDING'); // PENDING, PAID, FAILED, REFUNDED, PARTIALLY_REFUNDED, RETAINED
                $table->dateTime('paid_at')->nullable();
                $table->dateTime('refunded_at')->nullable();
                $table->decimal('refund_amount', 12, 2)->default(0.00);
                $table->string('refund_reason', 255)->nullable();
                $table->unsignedBigInteger('refunded_by')->nullable();
                $table->json('raw_response')->nullable();
                $table->timestamps();

                $table->foreign('reservation_id')->references('id')->on('reservations')->cascadeOnDelete();
                $table->index(['status', 'created_at'], 'idx_res_payments_status_date');
            });
        }

        // 6. Tabla de Lista de Espera (reservation_waitlists)
        if (! Schema::hasTable('reservation_waitlists')) {
            Schema::create('reservation_waitlists', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->string('customer_name', 100);
                $table->string('customer_phone', 30);
                $table->string('customer_email', 190)->nullable();
                $table->date('desired_date');
                $table->string('desired_time', 10);
                $table->unsignedTinyInteger('guest_count');
                $table->string('status', 20)->default('WAITING'); // WAITING, NOTIFIED, CONVERTED, EXPIRED, CANCELLED
                $table->dateTime('notified_at')->nullable();
                $table->dateTime('expires_at')->nullable();
                $table->string('notes', 300)->nullable();
                $table->timestamps();

                $table->index(['desired_date', 'status'], 'idx_waitlist_date_status');
            });
        }

        // 7. Auditoría de Reservas (reservation_audit_logs)
        if (! Schema::hasTable('reservation_audit_logs')) {
            Schema::create('reservation_audit_logs', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('reservation_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('action', 60);
                $table->json('details')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->dateTime('created_at')->useCurrent();

                $table->index(['action', 'created_at'], 'idx_res_audit_action_date');
            });
        }

        // 8. Códigos de Verificación de Teléfono / OTP (customer_verification_codes)
        if (! Schema::hasTable('customer_verification_codes')) {
            Schema::create('customer_verification_codes', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->string('phone', 30)->index();
                $table->string('code_hash', 64);
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->unsignedTinyInteger('resends')->default(0);
                $table->boolean('verified')->default(false);
                $table->string('ip_address', 45)->nullable();
                $table->dateTime('expires_at');
                $table->timestamps();

                $table->index(['phone', 'verified', 'expires_at'], 'idx_otp_lookup');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_verification_codes');
        Schema::dropIfExists('reservation_audit_logs');
        Schema::dropIfExists('reservation_waitlists');
        Schema::dropIfExists('reservation_payments');
        Schema::dropIfExists('reservation_holds');

        Schema::table('reservations', function (Blueprint $table): void {
            $cols = [
                'advance_amount', 'applied_credit_amount', 'payment_status',
                'payment_expires_at', 'arrival_deadline', 'checked_in_at',
                'seated_at', 'cancelled_at', 'cancellation_reason',
                'policy_version', 'dining_session_id', 'order_id', 'hold_token',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('reservations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('restaurant_tables', function (Blueprint $table): void {
            if (Schema::hasColumn('restaurant_tables', 'is_online_reservable')) {
                $table->dropColumn('is_online_reservable');
            }
        });

        Schema::dropIfExists('reservation_configurations');
    }
};
