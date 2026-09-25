<?php

namespace Tests\Feature;

use App\Filament\Support\CachedWidgetCounts;
use App\Models\Farrier;
use App\Models\Stable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CachedWidgetCountsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);

        Schema::create('stables', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('type_id')->nullable();
        });
        Schema::create('farriers', function (Blueprint $table) {
            $table->id();
            $table->string('status');
        });
    }

    public function test_counts_total_and_matching_in_one_cached_query(): void
    {
        DB::table('stables')->insert([['is_active' => true], ['is_active' => true], ['is_active' => false]]);

        $this->assertSame(['total' => 3, 'matching' => 2], CachedWidgetCounts::totalAndMatching(Stable::class, 'is_active', true));

        // Cached: a new row does not show until the cache expires.
        DB::table('stables')->insert(['is_active' => true]);
        $this->assertSame(['total' => 3, 'matching' => 2], CachedWidgetCounts::totalAndMatching(Stable::class, 'is_active', true));

        Cache::flush();
        $this->assertSame(['total' => 4, 'matching' => 3], CachedWidgetCounts::totalAndMatching(Stable::class, 'is_active', true));
    }

    public function test_matches_a_string_status(): void
    {
        DB::table('farriers')->insert([['status' => 'active'], ['status' => 'pending']]);

        $this->assertSame(['total' => 2, 'matching' => 1], CachedWidgetCounts::totalAndMatching(Farrier::class, 'status', 'active'));
    }

    public function test_empty_table_counts_zero(): void
    {
        $this->assertSame(['total' => 0, 'matching' => 0], CachedWidgetCounts::totalAndMatching(Stable::class, 'is_active', true));
    }

    public function test_groups_by_a_column(): void
    {
        DB::table('stables')->insert([['type_id' => 1], ['type_id' => 1], ['type_id' => 3]]);

        $this->assertSame([1 => 2, 3 => 1], CachedWidgetCounts::groupedBy(Stable::class, 'type_id'));
    }
}
