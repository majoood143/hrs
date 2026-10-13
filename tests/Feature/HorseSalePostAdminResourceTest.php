<?php

namespace Tests\Feature;

use App\Filament\Resources\HorseSalePostResource;
use App\Filament\Resources\HorseSalePostResource\Pages\EditHorseSalePost;
use App\Filament\Resources\HorseSalePostResource\Pages\ListHorseSalePosts;
use App\Filament\Resources\HorseSalePostResource\Pages\ViewHorseSalePost;
use App\Models\HorseSalePost;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\PreparesMobileApi;
use Tests\TestCase;

/** The admin Horses for Sale screen: both languages and its uploads. */
class HorseSalePostAdminResourceTest extends TestCase
{
    use PreparesMobileApi;

    private HorseSalePost $post;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareMobileApi();
        (require database_path('migrations/2026_10_13_000001_add_pending_status_to_public_posts.php'))->up();
        $this->withoutVite();
        Storage::fake('public');
        Storage::disk('public')->put('horse-sale/covers/a.jpg', UploadedFile::fake()->image('a.jpg')->getContent());
        $ids = $this->seedLocations();

        $this->post = HorseSalePost::query()->forceCreate([
            'en_name' => 'Desert Wind', 'ar_name' => 'ريح الصحراء',
            'type_id' => DB::table('types')->insertGetId(['en_name' => 'Arabian', 'ar_name' => 'عربي']),
            'gender_id' => DB::table('genders')->insertGetId(['en_name' => 'Mare', 'ar_name' => 'فرس']),
            'color_id' => DB::table('colors')->insertGetId(['en_name' => 'Bay', 'ar_name' => 'أحمر', 'hex_code' => '#8b4513']),
            'country_id' => $ids['oman'], 'region_id' => $ids['muscat'], 'city_id' => $ids['seeb'],
            'price' => 1500, 'status' => 'active', 'contact_number' => '+96891234567', 'cover_photo' => 'horse-sale/covers/a.jpg',
        ]);

        Gate::before(fn () => true);
        $this->actingAs(new class(['name' => 'Admin']) extends Authenticatable
        {
            protected $guarded = [];
        });
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_the_arabic_admin_shows_arabic_labels_lookups_and_statuses(): void
    {
        App::setLocale('ar');

        Livewire::test(ListHorseSalePosts::class)
            ->assertOk()
            ->assertSee('ريح الصحراء')
            ->assertSee('عربي')
            ->assertSee('فرس')
            ->assertSee('السيب')
            ->assertSee('نشط')
            ->assertDontSee('Arabian');

        Livewire::test(EditHorseSalePost::class, ['record' => $this->post->getKey()])
            ->assertOk()
            ->assertSee(__('admin_horse_sale_post.tabs.pedigree'))
            ->assertFormFieldExists('type_id', fn (Select $field) => $field->getOptionLabel() === 'عربي')
            ->assertFormFieldExists('color_id', fn (Select $field) => $field->getOptionLabel() === 'أحمر')
            ->assertFormFieldExists('status', fn (Select $field) => $field->getOptionLabel() === 'نشط');
    }

    public function test_the_passport_must_be_an_image_or_pdf(): void
    {
        Livewire::test(EditHorseSalePost::class, ['record' => $this->post->getKey()])
            ->fillForm(['passport_document' => UploadedFile::fake()->create('passport.svg', 10, 'image/svg+xml')])
            ->call('save')
            ->assertHasFormErrors(['passport_document']);

        Livewire::test(EditHorseSalePost::class, ['record' => $this->post->getKey()])
            ->fillForm(['en_name' => 'Desert Wind II', 'passport_document' => UploadedFile::fake()->create('passport.pdf', 100, 'application/pdf')])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Desert Wind II', $this->post->refresh()->en_name);
        $this->assertNotNull($this->post->passport_document);
    }

    public function test_a_post_waiting_for_approval_is_flagged_and_approved_from_the_list_or_its_page(): void
    {
        $this->assertSame('1', HorseSalePostResource::getNavigationBadge());
        $this->assertNull(HorseSalePostResource::getNavigationBadgeColor());

        $this->post->update(['status' => 'pending']);
        $second = $this->post->replicate();
        $second->save();
        App::setLocale('ar');

        $this->assertSame('2', HorseSalePostResource::getNavigationBadge());
        $this->assertSame('warning', HorseSalePostResource::getNavigationBadgeColor());

        Livewire::test(ListHorseSalePosts::class)
            ->assertOk()
            ->assertSee('بانتظار الموافقة')
            ->assertActionVisible(TestAction::make('approvePost')->table($this->post))
            ->callAction(TestAction::make('approvePost')->table($this->post));

        $this->assertSame('active', $this->post->refresh()->status);

        Livewire::test(ListHorseSalePosts::class)
            ->assertActionHidden(TestAction::make('approvePost')->table($this->post));

        Livewire::test(ViewHorseSalePost::class, ['record' => $second->getKey()])
            ->assertActionVisible('approvePost')
            ->callAction('approvePost');

        $this->assertSame('active', $second->refresh()->status);
        $this->assertSame('2', HorseSalePostResource::getNavigationBadge());
        $this->assertNull(HorseSalePostResource::getNavigationBadgeColor());
    }

    public function test_approving_several_leaves_the_posts_that_were_not_waiting_alone(): void
    {
        $sold = $this->post->replicate()->forceFill(['status' => 'sold']);
        $sold->save();
        $pending = $this->post->replicate()->forceFill(['status' => 'pending']);
        $pending->save();

        Livewire::test(ListHorseSalePosts::class)
            ->selectTableRecords([$sold->getKey(), $pending->getKey()])
            ->callAction(TestAction::make('approvePosts')->table()->bulk());

        $this->assertSame('sold', $sold->refresh()->status);
        $this->assertSame('active', $pending->refresh()->status);
    }
}
