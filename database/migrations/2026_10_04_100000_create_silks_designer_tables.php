<?php

use App\Support\Silks\SilksDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('silk_colors', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('en_name');
            $table->string('ar_name');
            $table->string('hex', 7);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('silk_patterns', function (Blueprint $table) {
            $table->id();
            $table->string('area', 10);
            $table->string('key', 60);
            $table->string('en_name');
            $table->string('ar_name');
            $table->text('svg');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['area', 'key']);
        });

        $now = now();

        DB::table('silk_colors')->insert(array_map(
            fn (array $color, int $i) => $color + ['is_active' => true, 'sort' => $i + 1, 'created_at' => $now, 'updated_at' => $now],
            SilksDefaults::colors(), array_keys(SilksDefaults::colors()),
        ));

        DB::table('silk_patterns')->insert(array_map(
            fn (array $pattern, int $i) => $pattern + ['is_active' => true, 'sort' => $i + 1, 'created_at' => $now, 'updated_at' => $now],
            SilksDefaults::patterns(), array_keys(SilksDefaults::patterns()),
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('silk_patterns');
        Schema::dropIfExists('silk_colors');
    }
};
