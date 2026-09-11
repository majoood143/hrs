<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_menu_items', function (Blueprint $table) {
            $table->string('route_name')->nullable()->after('url');
            $table->boolean('is_button')->default(false)->after('target');
        });
    }

    public function down(): void
    {
        Schema::table('cms_menu_items', function (Blueprint $table) {
            $table->dropColumn(['route_name', 'is_button']);
        });
    }
};
