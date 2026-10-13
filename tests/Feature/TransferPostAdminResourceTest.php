<?php

namespace Tests\Feature;

use App\Filament\Public\Resources\TransferPostResource\Pages\ListTransferPosts as PublicListTransferPosts;
use App\Filament\Resources\TransferPostResource\Pages\EditTransferPost;
use App\Filament\Resources\TransferPostResource\Pages\ListTransferPosts;
use App\Models\TransferPost;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Concerns\PreparesMobileApi;
use Tests\Concerns\PreparesMobileApiDirectory;
use Tests\TestCase;

/** The admin Transfer Posts screen and the public /transportation board: both languages, routes, queries. */
class TransferPostAdminResourceTest extends TestCase
{
    use PreparesMobileApi;
    use PreparesMobileApiDirectory;

    /** @var array<string, int> */
    private array $ids;

    private TransferPost $post;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareMobileApi();
        $this->prepareDirectoryTables();
        $this->withoutVite();
        $this->ids = $this->seedLocations();

        $this->post = $this->transfer();

        Gate::before(fn () => true);
        $this->actingAs(new class(['name' => 'Admin']) extends Authenticatable
        {
            protected $guarded = [];
        });
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function transfer(): TransferPost
    {
        return TransferPost::query()->forceCreate([
            'type' => 'offer', 'status' => 'active', 'capacity' => 2, 'transfer_date' => now()->addWeek()->toDateString(),
            'price' => 40, 'contact_number' => '+96891234567',
            'from_country_id' => $this->ids['oman'], 'from_region_id' => $this->ids['muscat'], 'from_city_id' => $this->ids['seeb'],
            'to_country_id' => $this->ids['oman'], 'to_region_id' => $this->ids['muscat'], 'to_city_id' => $this->ids['bawshar'],
        ]);
    }

    public function test_the_arabic_admin_shows_arabic_labels_places_and_options(): void
    {
        App::setLocale('ar');

        Livewire::test(ListTransferPosts::class)
            ->assertOk()
            ->assertSee('السيب')
            ->assertSee('بوشر')
            ->assertSee('عرض')
            ->assertSee('نشط')
            ->assertSee(__('admin_transfer_post.fields.from_country'))
            ->assertDontSee('Seeb');

        Livewire::test(EditTransferPost::class, ['record' => $this->post->getKey()])
            ->assertOk()
            ->assertSee(__('admin_transfer_post.sections.to'))
            ->assertFormFieldExists('from_city_id', fn (Select $field) => $field->getOptionLabel() === 'السيب')
            ->assertFormFieldExists('to_city_id', fn (Select $field) => $field->getOptionLabel() === 'بوشر')
            ->assertFormFieldExists('type', fn (Select $field) => $field->getOptionLabel() === 'عرض');
    }

    public function test_a_route_must_go_somewhere_else_and_an_offer_needs_a_price(): void
    {
        Livewire::test(EditTransferPost::class, ['record' => $this->post->getKey()])
            ->fillForm(['to_city_id' => $this->ids['seeb'], 'price' => null])
            ->call('save')
            ->assertHasFormErrors(['to_city_id', 'price']);

        Livewire::test(EditTransferPost::class, ['record' => $this->post->getKey()])
            ->fillForm(['capacity' => 3])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(3, (int) $this->post->refresh()->capacity);
    }

    public function test_the_public_board_shows_arabic_routes_without_a_query_per_card(): void
    {
        App::setLocale('ar');
        Filament::setCurrentPanel(Filament::getPanel('public'));
        $this->transfer();
        $this->transfer();

        DB::enableQueryLog();
        Livewire::test(PublicListTransferPosts::class)
            ->assertOk()
            ->assertSee('السيب, عمان → بوشر, عمان', false)
            ->assertSee(__('transportation.filter_date_from'));

        $cityQueries = collect(DB::getQueryLog())->filter(fn (array $query) => str_contains($query['query'], 'from "cities"'));
        $this->assertLessThanOrEqual(2, $cityQueries->count());
    }
}
