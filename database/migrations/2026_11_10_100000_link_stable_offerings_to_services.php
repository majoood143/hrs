<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One list of services: an offering's type is now an admin stable service (admin → Stable Services,
 * "Bookable") instead of the code enum. Existing offerings (all `riding_training`) become
 * "Riding Lessons", and their stables get that service ticked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stable_services', function (Blueprint $table) {
            $table->boolean('is_bookable')->default(false)->after('ar_name');
        });

        Schema::table('stable_offerings', function (Blueprint $table) {
            $table->foreignId('stable_service_id')->nullable()->after('stable_id')->constrained('stable_services')->restrictOnDelete();
        });

        DB::table('stable_services')->whereIn('en_name', ['Riding Lessons', 'Training'])->update(['is_bookable' => true]);

        if (DB::table('stable_offerings')->exists()) {
            $lessons = DB::table('stable_services')->where('en_name', 'Riding Lessons')->value('id')
                ?? DB::table('stable_services')->insertGetId(['en_name' => 'Riding Lessons', 'ar_name' => 'دروس ركوب الخيل', 'is_bookable' => true, 'created_at' => now(), 'updated_at' => now()]);

            DB::table('stable_offerings')->update(['stable_service_id' => $lessons]);
            DB::table('stable_stable_service')->insertOrIgnore(
                DB::table('stable_offerings')->distinct()->pluck('stable_id')
                    ->map(fn ($stableId) => ['stable_id' => $stableId, 'stable_service_id' => $lessons])->all()
            );
        }

        Schema::table('stable_offerings', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }

    public function down(): void
    {
        Schema::table('stable_offerings', function (Blueprint $table) {
            $table->string('type', 32)->default('riding_training')->after('stable_id');
        });

        Schema::table('stable_offerings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stable_service_id');
        });

        Schema::table('stable_services', function (Blueprint $table) {
            $table->dropColumn('is_bookable');
        });
    }
};
