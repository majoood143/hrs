<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_menu_items', function (Blueprint $table) {
            $table->json('label_translations')->nullable()->after('label');
        });

        DB::table('cms_menu_items')->orderBy('id')->each(function (object $item) {
            DB::table('cms_menu_items')->where('id', $item->id)->update([
                'label_translations' => json_encode(['en' => $item->label, 'ar' => $item->label]),
            ]);
        });

        Schema::table('cms_menu_items', function (Blueprint $table) {
            $table->dropColumn('label');
        });

        Schema::table('cms_menu_items', function (Blueprint $table) {
            $table->renameColumn('label_translations', 'label');
        });
    }

    public function down(): void
    {
        Schema::table('cms_menu_items', function (Blueprint $table) {
            $table->string('label_plain')->nullable()->after('label');
        });

        DB::table('cms_menu_items')->orderBy('id')->each(function (object $item) {
            $translations = json_decode($item->label, true) ?? [];

            DB::table('cms_menu_items')->where('id', $item->id)->update([
                'label_plain' => $translations['en'] ?? reset($translations) ?: '',
            ]);
        });

        Schema::table('cms_menu_items', function (Blueprint $table) {
            $table->dropColumn('label');
        });

        Schema::table('cms_menu_items', function (Blueprint $table) {
            $table->renameColumn('label_plain', 'label');
        });
    }
};
