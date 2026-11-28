<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The "auction_manager" role: admin → Auctions (auctions, their bidders, lots, phone bids and voids;
 * Shield names). Give it from Users or Shield → Roles; super admins already have everything.
 */
return new class extends Migration
{
    private const ABILITIES = ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny'];

    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        $role = Role::findOrCreate('auction_manager', 'web');

        foreach (['Auction', 'AuctionLot'] as $model) {
            foreach (self::ABILITIES as $ability) {
                $role->givePermissionTo(Permission::findOrCreate("{$ability}:{$model}", 'web'));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (Schema::hasTable('roles')) {
            Role::query()->where('name', 'auction_manager')->where('guard_name', 'web')->delete();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
};
