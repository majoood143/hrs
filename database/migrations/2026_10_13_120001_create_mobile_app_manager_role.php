<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The "mobile_app_manager" role: the Mobile app settings page and the announcements (Shield names).
 * Give it to whoever runs the app from Users or Shield → Roles; super admins already have everything.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'View:MobileAppSettings',
        'ViewAny:MobileAnnouncement',
        'View:MobileAnnouncement',
        'Create:MobileAnnouncement',
        'Update:MobileAnnouncement',
        'Delete:MobileAnnouncement',
        'DeleteAny:MobileAnnouncement',
        'Replicate:MobileAnnouncement',
    ];

    public function up(): void
    {
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
        if (Schema::hasTable('roles')) {
            Role::query()->where('name', 'mobile_app_manager')->where('guard_name', 'web')->delete();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
