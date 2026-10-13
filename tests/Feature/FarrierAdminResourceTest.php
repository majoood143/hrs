<?php

namespace Tests\Feature;

use App\Filament\Resources\FarrierResource\Pages\EditFarrier;
use App\Filament\Resources\FarrierResource\Pages\ListFarriers;
use App\Models\Farrier;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\PreparesMobileApi;
use Tests\Concerns\PreparesMobileApiDirectory;
use Tests\TestCase;

/** The admin Farriers screen: both languages and its photo. */
class FarrierAdminResourceTest extends TestCase
{
    use PreparesMobileApi;
    use PreparesMobileApiDirectory;

    private Farrier $farrier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareMobileApi();
        $this->prepareDirectoryTables();
        $this->withoutVite();
        Storage::fake('public');
        Storage::disk('public')->put('farriers/covers/a.jpg', UploadedFile::fake()->image('a.jpg')->getContent());
        $ids = $this->seedLocations();

        $this->farrier = Farrier::query()->forceCreate([
            'en_name' => 'Salim Shoeing', 'ar_name' => 'سالم للحدادة', 'specialty' => 'Corrective', 'years_experience' => 12,
            'country_id' => $ids['oman'], 'region_id' => $ids['muscat'], 'city_id' => $ids['seeb'],
            'price' => 25, 'status' => 'active', 'contact_number' => '+96891234567', 'cover_photo' => 'farriers/covers/a.jpg',
        ]);

        Gate::before(fn () => true);
        $this->actingAs(new class(['name' => 'Admin']) extends Authenticatable
        {
            protected $guarded = [];
        });
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_the_arabic_admin_shows_arabic_labels_names_and_statuses(): void
    {
        App::setLocale('ar');

        Livewire::test(ListFarriers::class)
            ->assertOk()
            ->assertSee('سالم للحدادة')
            ->assertSee('السيب')
            ->assertSee('نشط')
            ->assertSee(__('farriers.specialty'))
            ->assertDontSee('Salim Shoeing');

        Livewire::test(EditFarrier::class, ['record' => $this->farrier->getKey()])
            ->assertOk()
            ->assertSee(__('admin_farrier.tabs.listing'))
            ->assertFormFieldExists('city_id', fn (Select $field) => $field->getOptionLabel() === 'السيب')
            ->assertFormFieldExists('status', fn (Select $field) => $field->getOptionLabel() === 'نشط');
    }

    public function test_the_photo_cannot_be_an_svg(): void
    {
        Livewire::test(EditFarrier::class, ['record' => $this->farrier->getKey()])
            ->fillForm(['cover_photo' => UploadedFile::fake()->create('x.svg', 5, 'image/svg+xml')])
            ->call('save')
            ->assertHasFormErrors(['cover_photo']);

        Livewire::test(EditFarrier::class, ['record' => $this->farrier->getKey()])
            ->fillForm(['en_name' => 'Salim Farrier'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Salim Farrier', $this->farrier->refresh()->en_name);
    }
}
