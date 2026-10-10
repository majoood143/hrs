<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Step 3 of the mobile API (directory, transfer board, events, blog, videos, Home): the tables those
 * endpoints read, built by hand with just the columns they use (several real migrations are
 * MySQL-only). Use with PreparesMobileApi (prepareMobileApi() first: locations and sale posts).
 */
trait PreparesMobileApiDirectory
{
    protected function prepareDirectoryTables(): void
    {
        Schema::create('farriers', function (Blueprint $table) {
            $table->id();
            $table->string('en_name');
            $table->string('ar_name')->nullable();
            $table->string('specialty')->nullable();
            $table->unsignedInteger('years_experience')->nullable();
            $table->foreignId('country_id')->nullable();
            $table->foreignId('region_id')->nullable();
            $table->foreignId('city_id')->nullable();
            $table->decimal('price', 10, 3)->nullable();
            $table->string('cover_photo')->nullable();
            $table->text('description_en')->nullable();
            $table->text('description_ar')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('status')->default('active');
            $table->unsignedBigInteger('views_count')->default(0);
            $table->timestamps();
        });

        Schema::create('transfer_posts', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            foreach (['from', 'to'] as $end) {
                $table->foreignId("{$end}_country_id")->nullable();
                $table->foreignId("{$end}_region_id")->nullable();
                $table->foreignId("{$end}_city_id")->nullable();
            }
            $table->unsignedInteger('capacity')->default(1);
            $table->date('transfer_date');
            $table->decimal('price', 10, 3)->nullable();
            $table->string('cover_photo')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('status')->default('active');
            $table->unsignedBigInteger('views_count')->default(0);
            $table->timestamps();
        });

        foreach (['stables', 'clinics', 'centers', 'shops'] as $places) {
            Schema::create($places, function (Blueprint $table) use ($places) {
                $table->id();
                $table->string('en_name');
                $table->string('ar_name')->nullable();
                $table->string('type')->nullable();
                $table->string('slug')->unique();
                $table->foreignId('country_id')->nullable();
                $table->foreignId('region_id')->nullable();
                $table->foreignId('city_id')->nullable();
                $table->string('address')->nullable();
                $table->string('map_link')->nullable();
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->string('website_url')->nullable();
                $table->string('instagram_url')->nullable();
                $table->text('en_description')->nullable();
                $table->text('ar_description')->nullable();
                $table->string('cover_photo')->nullable();
                $table->json('gallery')->nullable();
                $table->json('opening_hours')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('is_online')->default(false);
                $table->string('delivery_scope')->nullable();
                $table->json('payment_options')->nullable();
                $table->string('approval_status')->default('approved');
                if ($places !== 'stables') {
                    $table->unsignedBigInteger('views_count')->default(0);
                }
                $table->timestamps();
            });

            $singular = substr($places, 0, -1);

            Schema::create("{$singular}_services", function (Blueprint $table) {
                $table->id();
                $table->string('en_name');
                $table->string('ar_name')->nullable();
                $table->string('applies_to')->nullable();
                $table->timestamps();
            });

            Schema::create("{$singular}_{$singular}_service", function (Blueprint $table) use ($singular) {
                $table->foreignId("{$singular}_id");
                $table->foreignId("{$singular}_service_id");
            });
        }

        Schema::create('stable_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stable_id');
            $table->string('author_name')->nullable();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->text('reply')->nullable();
            $table->timestamps();
        });

        Schema::create('event_categories', function (Blueprint $table) {
            $table->id();
            $table->string('en_name');
            $table->string('ar_name')->nullable();
            $table->string('color')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->json('title');
            $table->json('description')->nullable();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->foreignId('category_id')->nullable();
            $table->string('link')->nullable();
            $table->unsignedBigInteger('views_count')->default(0);
            $table->timestamps();
        });

        Schema::create('cms_categories', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->string('slug')->unique();
            $table->json('description')->nullable();
            $table->foreignId('parent_id')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::create('cms_posts', function (Blueprint $table) {
            $table->id();
            $table->json('title');
            $table->string('slug')->unique();
            $table->json('excerpt')->nullable();
            $table->json('content')->nullable();
            $table->foreignId('category_id')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('views_count')->default(0);
            $table->timestamps();
        });

        Schema::create('cms_pages', function (Blueprint $table) {
            $table->id();
            $table->json('title')->nullable();
            $table->string('slug');
            $table->json('content')->nullable();
            $table->boolean('is_homepage')->default(false);
            $table->string('status')->default('published');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('video_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable();
            $table->json('name');
            $table->string('slug');
            $table->unsignedInteger('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id');
            $table->json('title');
            $table->json('description')->nullable();
            $table->string('slug');
            $table->string('youtube_url')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('views_count')->default(0);
            $table->timestamps();
        });

        Schema::create('ad_zones', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->string('slug');
            $table->unsignedInteger('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('media_type')->default('image');
            $table->string('image_path')->nullable();
            $table->string('video_path')->nullable();
            $table->json('alt_text')->nullable();
            $table->string('target_url')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('order')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->timestamps();
        });

        (require database_path('migrations/2026_09_07_152631_create_media_table.php'))->up();
    }

    /** A translatable JSON value. */
    protected function both(string $en, string $ar): string
    {
        return json_encode(['en' => $en, 'ar' => $ar], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Inserts a row without model events (no activity log), with timestamps when the table has them.
     */
    protected function row(string $table, array $values): int
    {
        $stamps = Schema::hasColumn($table, 'created_at') ? ['created_at' => now(), 'updated_at' => now()] : [];

        return DB::table($table)->insertGetId($values + $stamps);
    }
}
