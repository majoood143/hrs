<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * CRM segments (admin → CRM → Segments): saved rules over the customers' profiles and app events,
 * used as a push campaign audience (the next migration). The mobile_app_manager role may build them. Seeing a customer's
 * full ID number is its own permission, given to super_admin only.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'ViewAny:CrmSegment',
        'View:CrmSegment',
        'Create:CrmSegment',
        'Update:CrmSegment',
        'Delete:CrmSegment',
        'DeleteAny:CrmSegment',
    ];

    public const REVEAL = 'RevealNationalId:Customer';

    public function up(): void
    {
        Schema::create('crm_segments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            // App\Models\CrmSegment::rules(): event, counts, days, gender, countries, age, source, consent
            $table->json('rules');
            $table->timestamps();
        });

        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        $role = Role::findOrCreate('mobile_app_manager', 'web');

        foreach (self::PERMISSIONS as $name) {
            $role->givePermissionTo(Permission::findOrCreate($name, 'web'));
        }

        Permission::findOrCreate(self::REVEAL, 'web');

        // super admins hold each permission themselves here (Shield's gate bypass is off)
        Role::findOrCreate('super_admin', 'web')->givePermissionTo([...self::PERMISSIONS, self::REVEAL]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_segments');
    }
};
