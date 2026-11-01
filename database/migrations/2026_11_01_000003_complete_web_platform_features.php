<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_images')) {
            Schema::create('product_images', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedInteger('product_id');
                $table->string('image_url', 255);
                $table->string('alt_text', 150)->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
                $table->index(['product_id', 'sort_order'], 'idx_product_images_product_sort');
                $table->foreign('product_id', 'fk_product_images_product')->references('id')->on('products')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('product_option_groups')) {
            Schema::create('product_option_groups', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedInteger('product_id');
                $table->string('name', 100);
                $table->boolean('is_required')->default(false);
                $table->unsignedTinyInteger('max_selections')->default(1);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['product_id', 'is_active', 'sort_order'], 'idx_product_opt_groups_product');
                $table->foreign('product_id', 'fk_product_opt_groups_product')->references('id')->on('products')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('product_options')) {
            Schema::create('product_options', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('group_id');
                $table->string('name', 100);
                $table->decimal('price_delta', 10, 2)->default(0.00);
                $table->boolean('is_available')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
                $table->index(['group_id', 'is_available', 'sort_order'], 'idx_product_options_group');
                $table->foreign('group_id', 'fk_product_options_group')->references('id')->on('product_option_groups')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table): void {
                if (! Schema::hasColumn('order_items', 'notes')) {
                    $table->string('notes', 255)->nullable()->after('line_total');
                }
                if (! Schema::hasColumn('order_items', 'selected_options')) {
                    $table->json('selected_options')->nullable()->after('notes');
                }
            });
        }

        if (Schema::hasTable('product_reviews')) {
            Schema::table('product_reviews', function (Blueprint $table): void {
                if (! Schema::hasColumn('product_reviews', 'photo_url')) {
                    $table->string('photo_url', 255)->nullable()->after('comment');
                }
                if (! Schema::hasColumn('product_reviews', 'admin_reply')) {
                    $table->string('admin_reply', 1000)->nullable()->after('moderator_note');
                }
                if (! Schema::hasColumn('product_reviews', 'replied_at')) {
                    $table->dateTime('replied_at')->nullable()->after('admin_reply');
                }
                if (! Schema::hasColumn('product_reviews', 'helpful_count')) {
                    $table->unsignedInteger('helpful_count')->default(0)->after('replied_at');
                }
            });
        }

        if (Schema::hasTable('support_tickets')) {
            try {
                DB::statement("ALTER TABLE support_tickets MODIFY COLUMN category VARCHAR(40) NOT NULL DEFAULT 'other'");
            } catch (Throwable) {
                // Ignorar en motores que no requieran ALTER ENUM
            }
            Schema::table('support_tickets', function (Blueprint $table): void {
                if (! Schema::hasColumn('support_tickets', 'attachment_url')) {
                    $table->string('attachment_url', 255)->nullable()->after('subject');
                }
            });
        }

        if (Schema::hasTable('support_ticket_messages')) {
            Schema::table('support_ticket_messages', function (Blueprint $table): void {
                if (! Schema::hasColumn('support_ticket_messages', 'attachment_url')) {
                    $table->string('attachment_url', 255)->nullable()->after('message');
                }
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table): void {
                if (! Schema::hasColumn('users', 'avatar_url')) {
                    $table->string('avatar_url', 255)->nullable()->after('email');
                }
                if (! Schema::hasColumn('users', 'email_verified_at')) {
                    $table->dateTime('email_verified_at')->nullable()->after('last_login_at');
                }
            });
        }

        if (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table): void {
                if (! Schema::hasColumn('customers', 'avatar_url')) {
                    $table->string('avatar_url', 255)->nullable()->after('email');
                }
                if (! Schema::hasColumn('customers', 'phone_verified_at')) {
                    $table->dateTime('phone_verified_at')->nullable()->after('phone');
                }
                if (! Schema::hasColumn('customers', 'deleted_at')) {
                    $table->dateTime('deleted_at')->nullable()->after('updated_at');
                }
            });
        }

        if (Schema::hasTable('delivery_zones')) {
            Schema::table('delivery_zones', function (Blueprint $table): void {
                if (! Schema::hasColumn('delivery_zones', 'fee_confirmed')) {
                    $table->boolean('fee_confirmed')->default(false)->after('delivery_fee');
                }
                if (! Schema::hasColumn('delivery_zones', 'polygon_coordinates')) {
                    $table->json('polygon_coordinates')->nullable()->after('radius_km');
                }
            });
        }

        if (Schema::hasTable('order_delivery_details')) {
            Schema::table('order_delivery_details', function (Blueprint $table): void {
                if (! Schema::hasColumn('order_delivery_details', 'latitude')) {
                    $table->decimal('latitude', 10, 7)->nullable()->after('zone');
                }
                if (! Schema::hasColumn('order_delivery_details', 'longitude')) {
                    $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_options');
        Schema::dropIfExists('product_option_groups');
        Schema::dropIfExists('product_images');
    }
};
