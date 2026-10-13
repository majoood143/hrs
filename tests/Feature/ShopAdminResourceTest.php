<?php

namespace Tests\Feature;

use App\Filament\Resources\ShopResource\Pages\EditShop;
use App\Filament\Resources\ShopResource\Pages\ListShops;
use App\Filament\Resources\ShopServiceResource\Pages\ListShopServices;
use App\Http\Resources\V1\PlaceResource;
use App\Models\Shop;
use App\Models\ShopService;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Concerns\PreparesMobileApi;
use Tests\Concerns\PreparesMobileApiDirectory;
use Tests\TestCase;

/** The admin Shops screens: both languages and the rich text description. */
class ShopAdminResourceTest extends TestCase
{
    use PreparesMobileApi;
    use PreparesMobileApiDirectory;

    private Shop $shop;

    private ShopService $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareMobileApi();
        $this->prepareDirectoryTables();
        $this->withoutVite();
        $ids = $this->seedLocations();

        $this->category = ShopService::create(['en_name' => 'Saddles', 'ar_name' => 'سروج', 'applies_to' => 'tack']);
        $this->shop = Shop::query()->forceCreate([
            'en_name' => 'Tack Shop', 'ar_name' => 'متجر العدة', 'slug' => 'tack-shop', 'type' => 'tack',
            'country_id' => $ids['oman'], 'region_id' => $ids['muscat'], 'city_id' => $ids['seeb'],
            'is_active' => true, 'payment_options' => ['cash'],
        ]);
        $this->shop->services()->attach($this->category);

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

        Livewire::test(ListShops::class)
            ->assertOk()
            ->assertSee('متجر العدة')
            ->assertSee('السيب')
            ->assertSee('سروج')
            ->assertSee(__('shops.types.tack'))
            ->assertDontSee('Saddles');

        Livewire::test(EditShop::class, ['record' => $this->shop->slug])
            ->assertOk()
            ->assertSee(__('admin_shop.tabs.location'))
            ->assertFormFieldExists('services', fn (Select $field) => $field->getOptionLabels() === [$this->category->id => 'سروج'])
            ->assertFormFieldExists('type', fn (Select $field) => $field->getOptionLabel() === 'سروج وعدة')
            ->assertFormFieldExists('payment_options', fn (CheckboxList $field) => $field->getOptions()['cash'] === 'نقداً');

        Livewire::test(ListShopServices::class)
            ->assertOk()
            ->assertSee(__('admin_shop_service.fields.applies_to'))
            ->assertSee('سروج وعدة');
    }

    public function test_renaming_a_shop_keeps_its_slug_and_saves_rich_text(): void
    {
        Livewire::test(EditShop::class, ['record' => $this->shop->slug])
            ->fillForm(['en_name' => 'Tack Shop Muscat', 'en_description' => '<p>Saddles <strong>and</strong> bridles.</p>'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->shop->refresh();
        $this->assertSame('Tack Shop Muscat', $this->shop->en_name);
        $this->assertSame('tack-shop', $this->shop->slug);
        $this->assertSame('<p>Saddles <strong>and</strong> bridles.</p>', $this->shop->descriptionHtml());
        $this->assertSame('Saddles and bridles.', PlaceResource::make($this->shop)->toArray(Request::create('/'))['description']);
    }

    public function test_links_must_be_web_addresses(): void
    {
        Livewire::test(EditShop::class, ['record' => $this->shop->slug])
            ->fillForm(['website_url' => 'ftp://tack.example/x', 'instagram_url' => 'https://instagram.com/tack'])
            ->call('save')
            ->assertHasFormErrors(['website_url'])
            ->assertHasNoFormErrors(['instagram_url']);
    }
}
