<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Promo codes (admin → Payments → Promo codes, behind General Settings → Modules → Promo codes):
 * a discount off the price of a stable session or lesson package, like the booking project's. The
 * order keeps the code and the discount; a use is an order with the code that was not called off.
 * A game prize may carry a discount: giving the prizes then makes a single-use code bound to the
 * winner's mobile.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('description', 500)->nullable();
            $table->string('discount_type', 20); // percentage | fixed
            $table->decimal('discount_value', 10, 3);
            $table->decimal('max_discount_amount', 10, 3)->nullable(); // a percentage's cap
            $table->unsignedInteger('max_uses')->nullable(); // null = unlimited
            $table->dateTime('valid_from')->nullable();
            $table->dateTime('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('stable_id')->nullable()->constrained()->nullOnDelete(); // null = every stable
            $table->string('restricted_mobile', 20)->nullable()->index(); // a game winner's voucher
            $table->string('source', 30)->nullable(); // null (admin) | game
            $table->timestamps();
            $table->softDeletes();
        });

        if (Schema::hasTable('service_orders')) {
            Schema::table('service_orders', function (Blueprint $table) {
                $table->foreignId('promo_code_id')->nullable()->constrained()->nullOnDelete();
                $table->decimal('discount_amount', 10, 3)->default(0); // already taken off `price`
            });
        }

        if (Schema::hasTable('game_prizes')) {
            Schema::table('game_prizes', function (Blueprint $table) {
                $table->string('discount_type', 20)->nullable();
                $table->decimal('discount_value', 10, 3)->nullable();
                $table->decimal('max_discount_amount', 10, 3)->nullable();
                $table->unsignedSmallInteger('valid_days')->nullable();
            });
        }

        if (Schema::hasTable('game_awards')) {
            Schema::table('game_awards', function (Blueprint $table) {
                $table->foreignId('promo_code_id')->nullable()->constrained()->nullOnDelete();
            });
        }

        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        $names = collect(['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny'])
            ->map(fn (string $action) => Permission::findOrCreate("{$action}:PromoCode", 'web')->name)
            ->all();

        Role::findOrCreate('super_admin', 'web')->givePermissionTo($names);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (Schema::hasTable('game_awards')) {
            Schema::table('game_awards', fn (Blueprint $table) => $table->dropConstrainedForeignId('promo_code_id'));
        }
        if (Schema::hasTable('game_prizes')) {
            Schema::table('game_prizes', fn (Blueprint $table) => $table->dropColumn(['discount_type', 'discount_value', 'max_discount_amount', 'valid_days']));
        }
        if (Schema::hasTable('service_orders')) {
            Schema::table('service_orders', function (Blueprint $table) {
                $table->dropConstrainedForeignId('promo_code_id');
                $table->dropColumn('discount_amount');
            });
        }
        Schema::dropIfExists('promo_codes');
    }
};
