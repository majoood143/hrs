<?php

namespace Tests\Feature;

use App\Filament\Resources\AttachementResource\Pages\CreateAttachement;
use App\Filament\Resources\AuthorityResource\Pages\ListAuthorities;
use App\Filament\Resources\ColorResource\Pages\ListColors;
use App\Filament\Resources\GenderResource\Pages\ListGenders;
use App\Filament\Resources\HorseResource\Pages\CreateHorse;
use App\Filament\Resources\HorseResource\Pages\ListHorses;
use App\Filament\Resources\PollinationResource\Pages\ListPollinations;
use App\Filament\Resources\StableServiceResource\Pages\ListStableServices;
use App\Filament\Resources\StatusResource\Pages\ListStatuses;
use App\Filament\Resources\TransactionResource\Pages\CreateTransaction;
use App\Filament\Resources\TransactionResource\Pages\ListTransactions;
use App\Filament\Resources\TypeResource\Pages\ListTypes;
use App\Models\Attachement;
use App\Models\Color;
use App\Models\Gender;
use App\Models\Horse;
use App\Models\Transaction;
use App\Models\Type;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\PreparesMobileApi;
use Tests\TestCase;

/** The horse registry (horses, transactions, papers) and its lookup lists in the admin. */
class HorseRegistryAdminTest extends TestCase
{
    use PreparesMobileApi;

    private const MIGRATIONS = [
        '0001_01_01_000000_create_users_table.php',
        '2025_08_24_201524_create_permission_tables.php',
        '2025_09_29_070756_add_new_columns_to_users_tabel.php',
        '2025_08_14_170132_create_horses_table.php',
        '2025_08_26_185104_add_new_columns_to_horses_table.php',
        '2025_09_16_072147_adding_attachement_column_to_horses_tavle.php',
        '2025_09_29_125831_add_new_columns_to_horses_tabel.php',
        '2026_09_07_130006_add_website_profile_columns_to_horses_table.php',
        '2026_10_13_110000_repair_horses_table.php',
        '2025_09_29_120922_create_services_table.php',
        '2025_08_11_200624_create_statuses_table.php',
        '2025_08_11_200921_create_pollinations_table.php',
        '2025_08_11_201330_create_authorities_table.php',
        '2026_09_08_010000_create_stable_services_table.php',
        '2025_10_01_063758_create_transactions_table.php',
        '2025_10_01_075758_add_new_columns_to__transactions_tabel.php',
        '2025_09_29_103941_create_attachements_table.php',
        '2025_10_02_181044_add_new_columns_to_attachement_tabel.php',
    ];

    /** @var array<string, int> */
    private array $ids;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareMobileApi();
        foreach (self::MIGRATIONS as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
        $this->withoutVite();
        Storage::fake('local');
        Storage::fake('public');

        $this->ids = $this->seedLocations();
        $this->ids['arabian'] = DB::table('types')->insertGetId(['en_name' => 'Purebred Arabian', 'ar_name' => 'عربي أصيل']);
        $this->ids['stallion'] = DB::table('genders')->insertGetId(['en_name' => 'Stallion', 'ar_name' => 'حصان (فحل)']);
        $this->ids['mare'] = DB::table('genders')->insertGetId(['en_name' => 'Mare', 'ar_name' => 'فرس']);
        $this->ids['bay'] = DB::table('colors')->insertGetId(['en_name' => 'Bay', 'ar_name' => 'أحمر', 'hex_code' => '#8b4513']);

        $this->admin = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'secret-password', 'type' => 'admin']);
        Gate::before(fn () => true);
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function horse(array $attributes = []): Horse
    {
        return Horse::query()->forceCreate($attributes + [
            'en_name' => 'Desert Wind', 'ar_name' => 'ريح الصحراء', 'microchip' => '985112000000001', 'passport_number' => 'OM-1',
            'dob' => '2018-04-01', 'type_id' => $this->ids['arabian'], 'gender_id' => $this->ids['mare'], 'color_id' => $this->ids['bay'],
            'country_id' => $this->ids['oman'], 'region_id' => $this->ids['muscat'], 'city_id' => $this->ids['seeb'], 'user_id' => $this->admin->id,
        ]);
    }

    public function test_the_repair_migration_fixes_the_horses_table(): void
    {
        $this->assertTrue(Schema::hasColumns('horses', ['microchip', 'passport_number']));
        $this->assertFalse(Schema::hasColumn('horses', 'microship'));
        $foreignKeys = collect(Schema::getForeignKeys('horses'));
        $this->assertFalse($foreignKeys->contains(fn (array $key) => $key['columns'] === ['id']), 'A horse id is no longer tied to a colour id.');
        $this->assertSame('set null', strtolower((string) $foreignKeys->firstWhere('columns', ['dam_id'])['on_delete']));

        // More horses than colours, then a parent deleted: the offspring stays, its link is cleared.
        $dam = $this->horse(['microchip' => 'A1']);
        $foal = $this->horse(['microchip' => 'A2', 'dam_id' => $dam->id]);
        $this->horse(['microchip' => 'A3']);
        $dam->delete();
        $this->assertNull($foal->refresh()->dam_id);
    }

    public function test_a_horse_is_created_in_arabic_with_translated_lookups(): void
    {
        App::setLocale('ar');
        $dam = $this->horse(['microchip' => 'DAM-1']);
        $owner = User::query()->create(['name' => 'Salim', 'email' => 'salim@example.com', 'password' => 'secret-password', 'type' => 'owner']);

        Livewire::test(CreateHorse::class)
            ->assertFormFieldExists('type_id', fn (Select $field) => $field->getOptions() === [$this->ids['arabian'] => 'عربي أصيل'])
            ->assertFormFieldExists('dam_id', fn (Select $field) => $field->getOptions() === [$dam->id => 'ريح الصحراء · فرس'])
            ->fillForm([
                'en_name' => 'Sea Breeze', 'ar_name' => 'نسيم البحر', 'microchip' => '985112000000777', 'passport_number' => 'OM-777',
                'dob' => '2020-01-01', 'type_id' => $this->ids['arabian'], 'gender_id' => $this->ids['stallion'], 'dam_id' => $dam->id,
                'country_id' => $this->ids['oman'], 'region_id' => $this->ids['muscat'], 'city_id' => $this->ids['seeb'], 'user_id' => $owner->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $horse = Horse::query()->where('microchip', '985112000000777')->firstOrFail();
        $this->assertSame('OM-777', $horse->passport_number);
        $this->assertSame($dam->id, $horse->dam->id);
        $this->assertSame($owner->id, $horse->user_id);

        Livewire::test(ListHorses::class)
            ->assertOk()
            ->assertSee('نسيم البحر')
            ->assertSee('عربي أصيل')
            ->assertSee(__('admin_horse.fields.owner'));
    }

    public function test_the_qr_code_is_drawn_here_not_by_a_third_party(): void
    {
        $horse = $this->horse();

        Livewire::test(ListHorses::class)
            ->callTableAction('printQR', $horse)
            ->assertFileDownloaded('horse-985112000000001.png');
    }

    public function test_a_transaction_with_its_papers_is_saved_privately(): void
    {
        $horse = $this->horse();
        $serviceId = DB::table('services')->insertGetId(['name' => 'Registration', 'price' => 25, 'is_active' => true]);

        Livewire::test(CreateTransaction::class)
            ->fillForm([
                'horse_id' => $horse->id, 'service_id' => $serviceId, 'amount' => 25, 'currency' => 'OMR', 'status' => 'pending',
                'attachement' => [['type' => 'passport', 'name' => 'Passport', 'file_path' => [UploadedFile::fake()->create('passport.pdf', 50, 'application/pdf')]]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $transaction = Transaction::query()->firstOrFail();
        $this->assertSame($this->admin->id, $transaction->user_id);
        $paper = $transaction->attachement()->firstOrFail();
        $this->assertSame($horse->id, $paper->horse_id);
        $this->assertSame($this->admin->id, $paper->user_id);
        Storage::disk('local')->assertExists($paper->file_path);
        $this->assertSame([], Storage::disk('public')->allFiles());

        App::setLocale('ar');
        Livewire::test(ListTransactions::class)->assertOk()->assertSee('قيد الانتظار')->assertSee('ريح الصحراء');
    }

    public function test_a_paper_must_be_a_pdf_or_an_image(): void
    {
        $horse = $this->horse();

        Livewire::test(CreateAttachement::class)
            ->fillForm(['type' => 'passport', 'horse_id' => $horse->id, 'file_path' => [UploadedFile::fake()->create('x.html', 5, 'text/html')]])
            ->call('create')
            ->assertHasFormErrors(['file_path']);

        $this->assertSame(0, Attachement::query()->count());
    }

    public function test_lookups_are_translated_and_kept_while_horses_use_them(): void
    {
        App::setLocale('ar');
        Livewire::test(ListTypes::class)->assertOk()->assertSee(__('admin_lookups.fields.ar_name'));
        Livewire::test(ListStatuses::class)->assertOk()->assertSee(__('admin_lookups.fields.en_name'));
        Livewire::test(ListColors::class)->assertOk()->assertSee(__('admin_lookups.fields.hex_code'));
        foreach ([ListAuthorities::class, ListPollinations::class, ListStableServices::class] as $page) {
            Livewire::test($page)->assertOk()->assertSee(__('admin_lookups.fields.ar_name'));
        }
        App::setLocale('en');

        $this->horse();
        Livewire::test(ListTypes::class)->callTableAction(DeleteAction::class, $this->ids['arabian'])->assertNotified(__('admin_lookups.in_use.title'));
        Livewire::test(ListGenders::class)->callTableAction(DeleteAction::class, $this->ids['stallion']);

        $this->assertNotNull(Type::find($this->ids['arabian']), 'A type in use (deleting it would delete its horses) stays.');
        $this->assertNull(Gender::find($this->ids['stallion']), 'An unused gender goes.');
        $this->assertFalse(Color::find($this->ids['bay'])->delete());
        $this->assertSame(1, Horse::query()->count());
    }
}
