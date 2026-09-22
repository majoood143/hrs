<?php

namespace Tests\Concerns;

use App\Models\SiteSetting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * The project's migrations are MySQL-only, so instead of RefreshDatabase a test gives the site
 * layout and the 404 fallback just what they read: empty site settings, menus and redirects.
 */
trait PreparesSiteLayout
{
    protected function prepareSiteLayout(): void
    {
        Cache::flush();
        Cache::forever('site_settings.all', collect());
        SiteSetting::resetMemo();

        Schema::create('cms_menus', function (Blueprint $table) {
            $table->id();
            $table->string('location')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('cms_redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_path')->nullable();
            $table->string('to_path')->nullable();
            $table->unsignedInteger('hits')->default(0);
            $table->timestamps();
        });
    }
}
