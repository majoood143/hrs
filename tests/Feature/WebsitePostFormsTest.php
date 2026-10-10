<?php

namespace Tests\Feature;

use App\Events\PublicPostSubmitted;
use App\Models\Farrier;
use App\Models\HorseSalePost;
use App\Models\TransferPost;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\PreparesMobileApi;
use Tests\Concerns\PreparesMobileApiDirectory;
use Tests\Concerns\PreparesSiteLayout;
use Tests\TestCase;

/**
 * The website's post forms create through the same PublicPostCreator classes as the mobile API,
 * still guarded by the captcha and keeping the visitor's own contact number.
 */
class WebsitePostFormsTest extends TestCase
{
    use PreparesMobileApi;
    use PreparesMobileApiDirectory;
    use PreparesSiteLayout;

    /** @var array<string, int> */
    private array $ids;

    private string $captcha;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareSiteLayout();
        $this->prepareMobileApi();
        $this->prepareDirectoryTables();
        $this->ids = $this->seedLocations();
        $this->ids['type'] = DB::table('types')->insertGetId(['en_name' => 'Arabian', 'ar_name' => 'عربي']);
        $this->ids['gender'] = DB::table('genders')->insertGetId(['en_name' => 'Mare', 'ar_name' => 'فرس']);
        $this->captcha = str_repeat('a', config('filament-captcha.length', 5));

        Storage::fake('public');
        Event::fake([PublicPostSubmitted::class]);
    }

    public function test_a_horse_is_posted_with_its_gallery_and_the_visitors_number(): void
    {
        $this->withSession(['filament_captcha_code' => $this->captcha])
            ->post(route('horses-for-sale.store'), [
                'en_name' => 'Desert Wind', 'ar_name' => 'ريح الصحراء', 'type_id' => $this->ids['type'], 'gender_id' => $this->ids['gender'],
                'city_id' => $this->ids['bawshar'], 'price' => '900', 'price_negotiable' => '1',
                'cover_photo' => UploadedFile::fake()->image('cover.jpg'),
                'images' => [UploadedFile::fake()->image('a.jpg')],
                'contact_number' => '9123 4567', 'captcha' => $this->captcha,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('horses-for-sale.index'));

        $post = HorseSalePost::sole();
        $this->assertSame('9123 4567', $post->contact_number);
        $this->assertTrue($post->price_negotiable);
        $this->assertSame((int) $this->ids['muscat'], (int) $post->region_id);
        $this->assertCount(1, $post->images);
        Event::assertDispatched(PublicPostSubmitted::class);
    }

    public function test_the_captcha_is_still_required(): void
    {
        $this->withSession(['filament_captcha_code' => $this->captcha])
            ->post(route('farriers.store'), [
                'en_name' => 'Ali', 'ar_name' => 'علي', 'city_id' => $this->ids['seeb'], 'price' => '15',
                'cover_photo' => UploadedFile::fake()->image('ali.jpg'), 'contact_number' => '91234567', 'captcha' => 'wrong',
            ])
            ->assertSessionHasErrors('captcha');

        $this->assertSame(0, Farrier::count());
    }

    public function test_farriers_and_transfers_are_posted(): void
    {
        $this->withSession(['filament_captcha_code' => $this->captcha])
            ->post(route('farriers.store'), [
                'en_name' => 'Ali', 'ar_name' => 'علي', 'city_id' => $this->ids['seeb'], 'price' => '15', 'years_experience' => '8',
                'cover_photo' => UploadedFile::fake()->image('ali.jpg'), 'contact_number' => '91234567', 'captcha' => $this->captcha,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('farriers.index'));

        $this->withSession(['filament_captcha_code' => $this->captcha])
            ->post(route('transfer-board.store'), [
                'type' => 'request', 'from_city_id' => $this->ids['seeb'], 'to_city_id' => $this->ids['bawshar'], 'capacity' => '2',
                'transfer_date' => now()->addDay()->toDateString(), 'price' => '70', 'contact_number' => '91234567', 'captcha' => $this->captcha,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('transfer-board.index'));

        $this->assertSame(8, Farrier::sole()->years_experience);
        $this->assertNull(TransferPost::sole()->price, 'requests carry no price');
    }
}
