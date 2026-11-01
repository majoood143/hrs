<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Posts reported from the app (App Store guideline 1.2: user-generated content needs a way to report
 * it), listed in admin → Mobile app → Reports; the mobile_app_manager role may handle them.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'ViewAny:ContentReport',
        'View:ContentReport',
        'Update:ContentReport',
        'Delete:ContentReport',
        'DeleteAny:ContentReport',
    ];

    public function up(): void
    {
        Schema::create('content_reports', function (Blueprint $table) {
            $table->id();
            // ContentReport::SECTIONS: horses-for-sale | tools-for-sale | farriers | transfer-board | jockeys
            $table->string('section', 30);
            $table->unsignedBigInteger('post_id');
            // ContentReport::REASONS
            $table->string('reason', 30);
            $table->text('details')->nullable();
            $table->string('ip', 45)->nullable();
            // open | resolved
            $table->string('status', 20)->default('open');
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['section', 'post_id']);
        });

        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        $role = Role::findOrCreate('mobile_app_manager', 'web');

        foreach (self::PERMISSIONS as $name) {
            $role->givePermissionTo(Permission::findOrCreate($name, 'web'));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('content_reports');
    }
};
