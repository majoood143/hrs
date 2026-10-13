<?php

namespace Tests\Feature;

use App\Filament\Resources\CenterResource\Pages\EditCenter;
use App\Filament\Resources\CenterResource\Pages\ListCenters;
use App\Filament\Resources\CenterServiceResource\Pages\ListCenterServices;
use App\Filament\Resources\ClinicResource\Pages\EditClinic;
use App\Filament\Resources\ClinicResource\Pages\ListClinics;
use App\Filament\Resources\ClinicServiceResource\Pages\ListClinicServices;
use App\Http\Resources\V1\PlaceResource;
use App\Models\Center;
use App\Models\CenterService;
use App\Models\Clinic;
use App\Models\ClinicService;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Concerns\PreparesMobileApi;
use Tests\Concerns\PreparesMobileApiDirectory;
use Tests\TestCase;

/** The admin Clinics and Centers screens (one shared schema): both languages and rich text. */
class ClinicCenterAdminResourceTest extends TestCase
{
    use PreparesMobileApi;
    use PreparesMobileApiDirectory;

    private Clinic $clinic;

    private Center $center;

    private ClinicService $clinicService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareMobileApi();
        $this->prepareDirectoryTables();
        $this->withoutVite();
        $ids = $this->seedLocations();
        $location = ['country_id' => $ids['oman'], 'region_id' => $ids['muscat'], 'city_id' => $ids['seeb'], 'is_active' => true];

        $this->clinicService = ClinicService::create(['en_name' => 'Dentistry', 'ar_name' => 'طب الأسنان', 'applies_to' => 'both']);
        $this->clinic = Clinic::query()->forceCreate(['en_name' => 'Vet Care', 'ar_name' => 'رعاية بيطرية', 'slug' => 'vet-care', 'type' => 'clinic'] + $location);
        $this->clinic->services()->attach($this->clinicService);

        $centerService = CenterService::create(['en_name' => 'Show jumping', 'ar_name' => 'قفز الحواجز', 'applies_to' => 'training']);
        $this->center = Center::query()->forceCreate(['en_name' => 'Riding Center', 'ar_name' => 'مركز الفروسية', 'slug' => 'riding-center', 'type' => 'training'] + $location);
        $this->center->services()->attach($centerService);

        Gate::before(fn () => true);
        $this->actingAs(new class(['name' => 'Admin']) extends Authenticatable
        {
            protected $guarded = [];
        });
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_the_arabic_admin_shows_arabic_labels_names_and_options(): void
    {
        App::setLocale('ar');

        Livewire::test(ListClinics::class)
            ->assertOk()
            ->assertSee('رعاية بيطرية')
            ->assertSee('السيب')
            ->assertSee('طب الأسنان')
            ->assertSee(__('clinics.types.clinic'))
            ->assertDontSee('Dentistry');

        Livewire::test(ListCenters::class)
            ->assertOk()
            ->assertSee('مركز الفروسية')
            ->assertSee('قفز الحواجز')
            ->assertSee('تدريب')
            ->assertDontSee('Show jumping');

        Livewire::test(EditClinic::class, ['record' => $this->clinic->slug])
            ->assertOk()
            ->assertSee(__('admin_directory.tabs.location'))
            ->assertSee(__('clinics.days.mon'))
            ->assertFormFieldExists('services', fn (Select $field) => $field->getOptionLabels() === [$this->clinicService->id => 'طب الأسنان'])
            ->assertFormFieldExists('type', fn (Select $field) => $field->getOptionLabel() === 'عيادة')
            ->assertFormFieldExists('city_id', fn (Select $field) => $field->getOptionLabel() === 'السيب');

        Livewire::test(ListClinicServices::class)->assertOk()->assertSee('كلاهما');
        Livewire::test(ListCenterServices::class)->assertOk()->assertSee('تدريب')->assertSee(__('admin_directory.fields.applies_to'));
    }

    public function test_renaming_keeps_the_slug_and_the_description_is_rich_text(): void
    {
        Livewire::test(EditCenter::class, ['record' => $this->center->slug])
            ->fillForm(['en_name' => 'Riding Center Muscat', 'en_description' => '<p>Lessons <em>daily</em>.</p><script>x()</script>'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->center->refresh();
        $this->assertSame('riding-center', $this->center->slug);
        $this->assertSame('<p>Lessons <em>daily</em>.</p>', $this->center->descriptionHtml());
        $this->assertSame('Lessons daily.', PlaceResource::make($this->center)->toArray(Request::create('/'))['description']);

        Livewire::test(EditClinic::class, ['record' => $this->clinic->slug])
            ->fillForm(['website_url' => 'javascript:alert(1)'])
            ->call('save')
            ->assertHasFormErrors(['website_url']);
    }
}
