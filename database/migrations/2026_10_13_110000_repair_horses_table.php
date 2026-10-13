<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repairs the horse registry table, which no admin form could save to:
 *
 * - `horses_id_foreign` tied each horse's own id to a colour id (a typo for `color_id`): a horse
 *   numbered past the last colour could not be inserted, and deleting colour 5 deleted horse 5.
 * - the form saves `microchip` and `passport_number`; the table had `microship` and no passport.
 * - deleting a dam or sire deleted every offspring (and theirs): now the parent link is cleared.
 * - `color_id` had no foreign key at all: now it points at colours and is cleared on delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Matched by column, not name: SQLite keys have no names (and on MySQL `['id']` is `horses_id_foreign`).
        $foreignKeys = collect(Schema::getForeignKeys('horses'))->pluck('columns');

        Schema::table('horses', function (Blueprint $table) use ($foreignKeys) {
            foreach (['id', 'dam_id', 'sire_id'] as $column) {
                if ($foreignKeys->contains([$column])) {
                    $table->dropForeign([$column]);
                }
            }
        });

        Schema::table('horses', function (Blueprint $table) {
            if (Schema::hasColumn('horses', 'microship') && ! Schema::hasColumn('horses', 'microchip')) {
                $table->renameColumn('microship', 'microchip');
            }

            if (! Schema::hasColumn('horses', 'passport_number')) {
                $table->string('passport_number')->nullable()->after('registration_number');
            }
        });

        // A colour id that points nowhere would break the new foreign key.
        DB::table('horses')->whereNotNull('color_id')->whereNotIn('color_id', DB::table('colors')->select('id'))->update(['color_id' => null]);

        Schema::table('horses', function (Blueprint $table) {
            $table->foreign('dam_id')->references('id')->on('horses')->nullOnDelete();
            $table->foreign('sire_id')->references('id')->on('horses')->nullOnDelete();
            $table->foreign('color_id')->references('id')->on('colors')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('horses', function (Blueprint $table) {
            $table->dropForeign(['dam_id']);
            $table->dropForeign(['sire_id']);
            $table->dropForeign(['color_id']);
            $table->dropColumn('passport_number');
            $table->renameColumn('microchip', 'microship');
        });

        // The old cascades come back; the colour/id typo does not.
        Schema::table('horses', function (Blueprint $table) {
            $table->foreign('dam_id')->references('id')->on('horses')->cascadeOnDelete();
            $table->foreign('sire_id')->references('id')->on('horses')->cascadeOnDelete();
        });
    }
};
