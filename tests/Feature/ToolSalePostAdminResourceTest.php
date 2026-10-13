<?php

namespace Tests\Feature;

use App\Filament\Resources\ToolSalePostResource\Pages\EditToolSalePost;
use App\Filament\Resources\ToolSalePostResource\Pages\ListToolSalePosts;
use App\Models\ToolSalePost;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\PreparesMobileApi;
use Tests\TestCase;

/** The admin Tools for Sale screen: both languages and its photo. */
class ToolSalePostAdminResourceTest extends TestCase
{
    use PreparesMobileApi;

    private ToolSalePost $post;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareMobileApi();
        $this->withoutVite();
        Storage::fake('public');
        Storage::disk('public')->put('tools-for-sale/covers/a.jpg', UploadedFile::fake()->image('a.jpg')->getContent());
        $ids = $this->seedLocations();

        $this->post = ToolSalePost::query()->forceCreate([
            'en_name' => 'Leather Saddle', 'ar_name' => 'سرج جلدي', 'category' => 'saddlery_tack', 'condition' => 'used',
            'country_id' => $ids['oman'], 'region_id' => $ids['muscat'], 'city_id' => $ids['seeb'],
            'price' => 120, 'price_negotiable' => true, 'status' => 'active', 'contact_number' => '+96891234567',
            'cover_photo' => 'tools-for-sale/covers/a.jpg',
        ]);

        Gate::before(fn () => true);
        $this->actingAs(new class(['name' => 'Admin']) extends Authenticatable
        {
            protected $guarded = [];
        });
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_the_arabic_admin_shows_arabic_labels_and_options(): void
    {
        App::setLocale('ar');

        Livewire::test(ListToolSalePosts::class)
            ->assertOk()
            ->assertSee('سرج جلدي')
            ->assertSee('سروج وأدوات ركوب')
            ->assertSee('مستعمل')
            ->assertSee('قابل للتفاوض')
            ->assertSee('السيب')
            ->assertSee('حالة الإعلان')
            ->assertDontSee('Leather Saddle');

        Livewire::test(EditToolSalePost::class, ['record' => $this->post->getKey()])
            ->assertOk()
            ->assertFormFieldExists('category', fn (Select $field) => $field->getOptionLabel() === 'سروج وأدوات ركوب')
            ->assertFormFieldExists('condition', fn (Select $field) => $field->getOptionLabel() === 'مستعمل')
            ->assertFormFieldExists('status', fn (Select $field) => $field->getOptionLabel() === 'نشط')
            ->assertFormFieldExists('city_id', fn (Select $field) => $field->getOptionLabel() === 'السيب');
    }

    public function test_the_photo_cannot_be_an_svg(): void
    {
        Livewire::test(EditToolSalePost::class, ['record' => $this->post->getKey()])
            ->fillForm(['cover_photo' => UploadedFile::fake()->create('x.svg', 5, 'image/svg+xml')])
            ->call('save')
            ->assertHasFormErrors(['cover_photo']);

        Livewire::test(EditToolSalePost::class, ['record' => $this->post->getKey()])
            ->fillForm(['en_name' => 'English Saddle'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('English Saddle', $this->post->refresh()->en_name);
    }
}
