<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_pages', function (Blueprint $table) {
            $table->text('custom_head_scripts')->nullable()->after('custom_css');
            $table->text('custom_body_scripts')->nullable()->after('custom_head_scripts');
        });

        Schema::table('cms_posts', function (Blueprint $table) {
            $table->text('custom_head_scripts')->nullable()->after('custom_css');
            $table->text('custom_body_scripts')->nullable()->after('custom_head_scripts');
        });
    }

    public function down(): void
    {
        Schema::table('cms_pages', function (Blueprint $table) {
            $table->dropColumn(['custom_head_scripts', 'custom_body_scripts']);
        });

        Schema::table('cms_posts', function (Blueprint $table) {
            $table->dropColumn(['custom_head_scripts', 'custom_body_scripts']);
        });
    }
};
