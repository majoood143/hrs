<?php

namespace Tests\Feature;

use App\Filament\Resources\TransferPostResource\Pages\EditTransferPost;
use App\Filament\Resources\TransferPostResource\Pages\ListTransferPosts;
use App\Models\TransferPost;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Concerns\PreparesMobileApi;
use Tests\Concerns\PreparesMobileApiDirectory;
use Tests\TestCase;

/** The admin Transfer Posts screen: both languages, routes, queries. */
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

    public function test_the_list_switch_turns_a_post_on_and_off_but_leaves_other_statuses_alone(): void
    {
        $post = $this->transfer();
        $id = (string) $post->getKey();
        $page = Livewire::test(ListTransferPosts::class);

        $post->update(['status' => 'active']);
        $page->call('updateTableColumnState', 'is_active', $id, false);
        $this->assertSame('cancelled', $post->refresh()->status);

        $page->call('updateTableColumnState', 'is_active', $id, true);
        $this->assertSame('active', $post->refresh()->status);

        foreach (['expired', 'pending'] as $locked) {
            $post->update(['status' => $locked]);
            $page->call('updateTableColumnState', 'is_active', $id, true);
            $this->assertSame($locked, $post->refresh()->status);
        }
    }
}
