<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Push campaigns (admin → Mobile app → Notifications): a bilingual message to an audience of phones,
 * sent now or at a set time; one delivery row per phone it went to (the app's inbox, opens, and the
 * daily cap). The mobile_app_manager role may run them.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'ViewAny:PushCampaign',
        'View:PushCampaign',
        'Create:PushCampaign',
        'Update:PushCampaign',
        'Delete:PushCampaign',
        'DeleteAny:PushCampaign',
        'Replicate:PushCampaign',
    ];

    public function up(): void
    {
        Schema::create('push_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('en_title', 120);
            $table->string('ar_title', 120);
            $table->string('en_body', 500)->nullable();
            $table->string('ar_body', 500)->nullable();
            $table->string('image_path')->nullable();
            // none | screen | url (like the announcements' button)
            $table->string('action_type', 10)->default('none');
            $table->string('action_screen', 40)->nullable();
            $table->string('action_url', 2048)->nullable();
            // who: PushCampaign::AUDIENCES, a topic (MobileDevice::TOPICS), platforms, languages, versions, recent use
            $table->string('audience', 20)->default('everyone');
            $table->string('topic', 20)->default('news');
            $table->json('platforms')->nullable();
            $table->json('locales')->nullable();
            $table->string('min_version', 14)->nullable();
            $table->string('max_version', 14)->nullable();
            $table->unsignedSmallInteger('active_days')->nullable();
            // draft | scheduled | sending | sent
            $table->string('status', 12)->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('recipients')->default(0);
            $table->unsignedInteger('accepted')->default(0);
            $table->unsignedInteger('opened')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
        });

        Schema::create('push_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('push_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mobile_device_id')->constrained()->cascadeOnDelete();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['push_campaign_id', 'mobile_device_id']);
            $table->index(['mobile_device_id', 'created_at']);
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
        Schema::dropIfExists('push_deliveries');
        Schema::dropIfExists('push_campaigns');
    }
};
