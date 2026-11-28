<?php

namespace Tests\Feature\Api\V1;

use App\Filament\Pages\IncomeReport;
use App\Filament\Resources\ExpenseCategoryResource\Pages\ManageExpenseCategories;
use App\Filament\Resources\ExpenseResource\Pages\ManageExpenses;
use App\Filament\Stable\Widgets\StableFinanceOverview;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\SiteSetting;
use App\Models\Stable;
use App\Models\StableHorse;
use App\Models\User;
use App\Policies\ExpensePolicy;
use App\Services\Reports\ClosedBooks;
use App\Services\Reports\ExpenseReport;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\PreparesStableBookings;
use Tests\TestCase;

/**
 * Expenses (General Settings → Modules → Expenses): a stable owner's own expenses in the app's My
 * stable (list, totals by category, profit after expenses, add/change/delete with a receipt), kept
 * apart from ours and from other stables'. "Now" is 2026-11-20 in Muscat.
 */
class OwnerExpensesTest extends TestCase
{
    use PreparesStableBookings;

    private User $owner;

    private Stable $stable;

    private ExpenseCategory $feed;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareStableBookings(['enabled_gateways' => ['demo'], 'module_expenses_enabled' => true]);
        (require database_path('migrations/2026_09_11_145142_create_personal_access_tokens_table.php'))->up();
        (require database_path('migrations/2026_11_29_000001_create_expenses_tables.php'))->up();
        (require database_path('migrations/2026_10_13_120000_create_mobile_announcements_table.php'))->up();
        Carbon::setTestNow('2026-11-20 06:00:00');
        Storage::fake('local', ['serve' => true]);

        $this->owner = $this->makeOwner();
        $this->stable = $this->makeStable($this->owner, ['slug' => 'desert-riders']);
        $this->feed = ExpenseCategory::query()->where('en_name', 'Feed')->sole();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function module(bool $on): void
    {
        $all = Cache::get('site_settings.all');
        $all['module_expenses_enabled'] = (object) ['key' => 'module_expenses_enabled', 'type' => 'boolean', 'value' => $on ? 'true' : 'false'];
        Cache::forever('site_settings.all', $all);
        SiteSetting::resetMemo();
    }

    private function token(): string
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/v1/owner/auth/login', ['email' => 'owner@example.com', 'password' => 'secret-password'])->assertOk()->json('token');
    }

    private function expense(array $attributes = []): Expense
    {
        return Expense::query()->create($attributes + [
            'stable_id' => $this->stable->id,
            'expense_category_id' => $this->feed->id,
            'spent_on' => '2026-11-10',
            'amount' => '45.500',
        ]);
    }

    public function test_the_feature_is_hidden_and_refused_while_the_module_is_off(): void
    {
        $token = $this->token();
        $this->assertSame('on', $this->getJson('/api/v1/app-status')->json('data.features.stable_expenses'));

        $this->module(false);

        $this->assertSame('hidden', $this->getJson('/api/v1/app-status')->json('data.features.stable_expenses'));
        $this->withToken($token)->getJson('/api/v1/owner/stables/desert-riders/expenses')->assertForbidden()->assertJsonPath('code', 'feature_disabled');
        $this->withToken($token)->getJson('/api/v1/owner/stables/desert-riders/income')->assertOk()->assertJsonPath('totals.expenses', null);
        $this->assertSame(0, ExpenseReport::forPeriod($this->stable, 'this_month')->total());
    }

    public function test_an_owner_adds_an_expense_with_a_receipt_and_sees_the_profit(): void
    {
        $horse = StableHorse::query()->create(['stable_id' => $this->stable->id, 'name' => 'Barq']);
        $token = $this->token();

        $created = $this->withToken($token)->post('/api/v1/owner/stables/desert-riders/expenses', [
            'category_id' => $this->feed->id,
            'spent_on' => '2026-11-18',
            'amount' => '12.250',
            'horse_id' => $horse->id,
            'vendor' => 'Al Batinah Feed',
            'receipt' => UploadedFile::fake()->image('receipt.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $created->assertJsonPath('data.amount.amount', '12.250')
            ->assertJsonPath('data.category.label', 'Feed')
            ->assertJsonPath('data.horse.label', 'Barq')
            ->assertJsonPath('data.receipt_type', 'image');
        $this->assertNotNull($created->json('data.receipt_url'));
        Storage::disk('local')->assertExists(Expense::query()->sole()->receipt);

        $this->expense(['amount' => '7.750', 'expense_category_id' => ExpenseCategory::query()->where('en_name', 'Vet')->value('id')]);
        $this->expense(['spent_on' => '2026-10-31', 'amount' => '100']); // last month

        $this->withToken($token)->getJson('/api/v1/owner/stables/desert-riders/expenses')
            ->assertOk()
            ->assertJsonPath('totals.count', 2)
            ->assertJsonPath('totals.expenses.amount', '20.000')
            ->assertJsonPath('totals.profit.amount', '-20.000')
            ->assertJsonPath('by_category.0.category.label', 'Feed')
            ->assertJsonPath('by_category.0.total.amount', '12.250')
            ->assertJsonPath('horses.0.label', 'Barq')
            ->assertJsonPath('data.0.date', '2026-11-18');

        $this->withToken($token)->getJson('/api/v1/owner/stables/desert-riders/expenses?period=last_month')->assertJsonPath('totals.expenses.amount', '100.000');
        $this->withToken($token)->getJson('/api/v1/owner/stables/desert-riders/income')->assertJsonPath('totals.expenses.amount', '20.000')->assertJsonPath('totals.profit.amount', '-20.000');
        $this->withToken($token)->getJson('/api/v1/owner/stables/desert-riders')->assertJsonPath('this_month.expenses.amount', '20.000');
    }

    public function test_refused_fields_come_back_in_arabic(): void
    {
        $other = $this->makeStable(null, ['slug' => 'other']);
        $theirHorse = StableHorse::query()->create(['stable_id' => $other->id, 'name' => 'Not ours']);
        $ours = ExpenseCategory::query()->create(['en_name' => 'Office', 'ar_name' => 'المكتب', 'scope' => 'platform']);

        $response = $this->withToken($this->token())->post('/api/v1/owner/stables/desert-riders/expenses', [
            'category_id' => $ours->id,
            'spent_on' => '2026-11-21',
            'amount' => '1.2345',
            'horse_id' => $theirHorse->id,
            'receipt' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        ], ['Accept' => 'application/json', 'Accept-Language' => 'ar'])->assertUnprocessable();

        $response->assertJsonValidationErrors(['category_id', 'spent_on', 'amount', 'horse_id', 'receipt']);
        foreach ($response->json('errors') as $messages) {
            $this->assertDoesNotMatchRegularExpression('/[A-Za-z_]{3,}/', preg_replace('/\b(JPG|PNG|WebP|PDF)\b/', '', $messages[0]));
        }
        $this->assertSame(0, Expense::query()->count());
    }

    public function test_an_owner_changes_and_deletes_only_their_own(): void
    {
        $token = $this->token();
        $old = UploadedFile::fake()->image('old.jpg')->store('expenses/receipts', 'local');
        $expense = $this->expense(['receipt' => $old]);
        $theirs = $this->expense(['stable_id' => $this->makeStable(null, ['slug' => 'other'])->id]);
        $platform = $this->expense(['stable_id' => null]);

        $this->withToken($token)->post("/api/v1/owner/stables/desert-riders/expenses/{$expense->id}", [
            'amount' => '50',
            'receipt' => UploadedFile::fake()->create('invoice.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.amount.amount', '50.000')->assertJsonPath('data.receipt_type', 'pdf')->assertJsonPath('data.date', '2026-11-10');
        Storage::disk('local')->assertMissing($old);

        $this->withToken($token)->post("/api/v1/owner/stables/desert-riders/expenses/{$expense->id}", ['remove_receipt' => 1], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('data.receipt_url', null);

        foreach ([$theirs, $platform] as $notMine) {
            $this->withToken($token)->postJson("/api/v1/owner/stables/desert-riders/expenses/{$notMine->id}", ['amount' => '1'])->assertNotFound();
            $this->withToken($token)->deleteJson("/api/v1/owner/stables/desert-riders/expenses/{$notMine->id}")->assertNotFound();
        }

        $this->withToken($token)->deleteJson("/api/v1/owner/stables/desert-riders/expenses/{$expense->id}")->assertNoContent();
        $this->assertSoftDeleted($expense);
        $this->assertSame('45.500', $theirs->fresh()->amount);
    }

    public function test_ours_and_the_stables_are_kept_apart_and_a_closed_month_is_locked(): void
    {
        $this->expense();
        $platform = $this->expense(['stable_id' => null, 'amount' => '3', 'spent_on' => '2026-10-15']);
        $this->expense(['stable_id' => null, 'amount' => '2']);

        $this->assertSame(45500, ExpenseReport::forPeriod($this->stable, 'this_month')->total());
        $this->assertSame(2000, ExpenseReport::forPeriod(null, 'this_month')->total());

        $admin = $this->makeAdminUser();
        $policy = new ExpensePolicy;
        $this->assertFalse($policy->update($this->owner, $platform));

        app(ClosedBooks::class)->close('2026-10', $admin);
        $admin->givePermissionTo(Permission::findOrCreate('Update:Expense'));
        $this->assertFalse($policy->update($admin->refresh(), $platform));
        $this->assertTrue($policy->update($admin, Expense::query()->platform()->where('spent_on', '2026-11-10')->sole()));
        $this->assertFalse($policy->update($admin, Expense::query()->where('stable_id', $this->stable->id)->sole()));
    }

    public function test_the_panels_show_expenses_and_profit_only_while_the_module_is_on(): void
    {
        $this->withoutVite();
        $this->expense(['vendor' => 'Al Batinah Feed']);
        $this->expense(['stable_id' => null, 'amount' => '8', 'vendor' => 'Office rent']);
        $this->actingAs($this->owner);

        $this->get('/stable/desert-riders/stable-expenses')->assertOk()->assertSee('Al Batinah Feed')->assertDontSee('Office rent');
        $this->get('/stable/desert-riders/statement')->assertOk()->assertSee('Profit after expenses')->assertSee('45.500');
        Filament::setCurrentPanel(Filament::getPanel('stable'));
        Filament::setTenant($this->stable);
        Livewire::test(StableFinanceOverview::class)->assertSee('Profit after expenses')->assertSee('-45.500');

        Gate::before(fn () => true);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(IncomeReport::class)->assertOk()->assertSee('Net after expenses')->assertSee('8.000')->assertDontSee('45.500');
        Livewire::test(ManageExpenses::class)->assertOk()->assertSee('Office rent')->assertDontSee('Al Batinah Feed');
        Livewire::test(ManageExpenseCategories::class)->assertOk()->assertSee('Farrier');
        Filament::setCurrentPanel(Filament::getPanel('stable'));

        $this->module(false);

        $this->get('/stable/desert-riders/stable-expenses')->assertForbidden();
        $this->get('/stable/desert-riders/statement')->assertOk()->assertDontSee('Profit after expenses');
    }
}
