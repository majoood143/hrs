<?php

namespace Tests\Feature;

use Tests\Concerns\PreparesMobileApi;
use Tests\Concerns\PreparesMobileApiDirectory;
use Tests\Concerns\PreparesSiteLayout;
use Tests\TestCase;

/**
 * The website's directory boards filter through the same model scopes as the mobile API
 * (filteredBy()/sortedBy() on Farrier, TransferPost and FiltersDirectoryPlaces).
 */
class DirectoryListingFiltersTest extends TestCase
{
    use PreparesMobileApi;
    use PreparesMobileApiDirectory;
    use PreparesSiteLayout;

    /** @var array<string, int> */
    private array $ids;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareSiteLayout();
        $this->prepareMobileApi();
        $this->prepareDirectoryTables();
        $this->ids = $this->seedLocations();
    }

    public function test_the_farriers_board_filters_by_specialty_and_sorts_by_price(): void
    {
        $this->row('farriers', ['en_name' => 'Hoof Hero', 'specialty' => 'Corrective', 'price' => 50, 'status' => 'active']);
        $this->row('farriers', ['en_name' => 'Trim Master', 'specialty' => 'Barefoot', 'price' => 20, 'status' => 'active']);

        $this->get(route('farriers.index', ['specialty' => 'corrective']))->assertOk()->assertSee('Hoof Hero')->assertDontSee('Trim Master');
        $this->get(route('farriers.index', ['sort' => 'price_asc']))->assertOk()->assertSeeInOrder(['Trim Master', 'Hoof Hero']);
    }

    public function test_the_transfer_board_filters_by_type_and_hides_past_dates(): void
    {
        $this->row('transfer_posts', ['type' => 'offer', 'transfer_date' => now()->addDay()->toDateString(), 'capacity' => 7, 'from_city_id' => $this->ids['seeb'], 'to_city_id' => $this->ids['bawshar'], 'status' => 'active']);
        $this->row('transfer_posts', ['type' => 'request', 'transfer_date' => now()->addDays(2)->toDateString(), 'capacity' => 9, 'from_city_id' => $this->ids['bawshar'], 'to_city_id' => $this->ids['seeb'], 'status' => 'active']);

        $page = $this->get(route('transfer-board.index', ['type' => 'request']))->assertOk();

        $this->assertSame(1, substr_count($page->getContent(), route('transfer-board.show', 2)));
        $this->assertSame(0, substr_count($page->getContent(), route('transfer-board.show', 1)));
    }

    public function test_the_place_boards_filter_by_type_and_service(): void
    {
        foreach (['clinics' => ['clinic', 'pharmacy'], 'centers' => ['training', 'breeding'], 'shops' => ['tack', 'equipment']] as $board => [$typeA, $typeB]) {
            $singular = substr($board, 0, -1);
            $service = $this->row("{$singular}_services", ['en_name' => "Service of {$board}", 'applies_to' => null]);
            $a = $this->row($board, ['en_name' => "Alpha {$board}", 'slug' => "alpha-{$board}", 'type' => $typeA, 'is_active' => true]);
            $this->row($board, ['en_name' => "Beta {$board}", 'slug' => "beta-{$board}", 'type' => $typeB, 'is_active' => true]);
            $this->row("{$singular}_{$singular}_service", ["{$singular}_id" => $a, "{$singular}_service_id" => $service]);

            $this->get(route("{$board}.index", ['type' => $typeB]))->assertOk()->assertSee("Beta {$board}")->assertDontSee("Alpha {$board}");
            $this->get(route("{$board}.index", ['service_id' => $service]))->assertOk()->assertSee("Alpha {$board}")->assertDontSee("Beta {$board}");
        }

        $this->row('stables', ['en_name' => 'Zed Stables', 'slug' => 'zed', 'is_active' => true]);
        $this->row('stables', ['en_name' => 'Ace Stables', 'slug' => 'ace', 'is_active' => true]);
        $this->row('stables', ['en_name' => 'Waiting Stables', 'slug' => 'waiting', 'is_active' => true, 'approval_status' => 'pending']);

        $this->get(route('stables.index'))->assertOk()->assertSeeInOrder(['Ace Stables', 'Zed Stables'])->assertDontSee('Waiting Stables');
    }
}
