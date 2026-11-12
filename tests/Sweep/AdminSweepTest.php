<?php

namespace Tests\Sweep;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ManageRecords;
use Filament\Resources\Pages\ViewRecord;
use Filament\Resources\RelationManagers\RelationGroup;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Health\ResultStores\EloquentHealthResultStore;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Throwable;

/**
 * Every admin screen, in English and Arabic, against a real MySQL database built from the
 * migrations (the project's migrations do not run on the sqlite of the other tests):
 * lists, create pages, edit and view pages of an existing record (and saving it unchanged),
 * relation managers, custom pages; plus untranslated keys showing in the page.
 *
 *   mysql -uroot -e "CREATE DATABASE hrs_testing"
 *   DB_DATABASE=hrs_testing php artisan migrate --force && DB_DATABASE=hrs_testing php artisan db:seed --force
 *   DB_DATABASE=hrs_testing APP_ENV=local php artisan db:seed --class=StableBookingDemoSeeder --force
 *   ADMIN_SWEEP_DB=hrs_testing php artisan test tests/Sweep
 *
 * Everything runs in a transaction that is rolled back, so the database stays as seeded.
 */
class AdminSweepTest extends TestCase
{
    /** @var list<string> */
    private array $problems = [];

    /** @var array<string, true> resources with no record, so no edit/view page was opened */
    private array $empty = [];

    protected function setUp(): void
    {
        parent::setUp();

        $database = getenv('ADMIN_SWEEP_DB') ?: null;

        if ($database === null) {
            $this->markTestSkipped('Set ADMIN_SWEEP_DB to a MySQL database built from the migrations (see the class comment).');
        }

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => $database,
            'health.result_stores.'.EloquentHealthResultStore::class.'.connection' => 'mysql',
        ]);
        DB::purge('mysql');
        DB::setDefaultConnection('mysql');
        DB::beginTransaction();

        Http::fake();
        Mail::fake();
        Notification::fake();
        Queue::fake();
        foreach (['public', 'local', 'private'] as $disk) {
            Storage::fake($disk);
        }
        $this->withoutVite();

    }

    protected function tearDown(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        parent::tearDown();
    }

    public function test_every_admin_screen_opens_and_saves_in_both_languages(): void
    {
        $admin = User::create(['name' => 'Sweep', 'email' => 'sweep-'.uniqid().'@example.com', 'password' => 'secret-password', 'type' => 'admin']);
        $admin->assignRole(Role::findOrCreate('super_admin', 'web'));
        // super_admin holds every permission in the real database (shield:generate); this checks the screens, not the roles
        Gate::before(fn () => true);
        $this->actingAs($admin);

        $this->sweepPanel('admin', function () {
            $this->problems = array_values(array_filter($this->problems, fn ($problem) => ! str_starts_with($problem, 'YouTubeChannelResource create')));
            $this->seedReadOnlyRecords();
        });
    }

    /**
     * The stable owners' panel as the demo owner (StableBookingDemoSeeder), in one of their stables,
     * with the owner's real permissions: no Gate override.
     */
    public function test_every_stable_panel_screen_opens_and_saves_for_an_owner(): void
    {
        $owner = User::query()->where('email', 'demo-owner@muhra.test')->first();

        if (! $owner || ! ($stable = $owner->stables()->orderBy('stables.id')->first())) {
            $this->markTestSkipped('Seed the demo stables first (StableBookingDemoSeeder, see the class comment).');
        }

        $this->actingAs($owner);
        Filament::setCurrentPanel(Filament::getPanel('stable'));
        Filament::setTenant($stable);
        Filament::bootCurrentPanel();

        $this->sweepPanel('stable');
    }

    private function sweepPanel(string $panelId, ?callable $afterCreating = null): void
    {
        Filament::setCurrentPanel(Filament::getPanel($panelId));
        Filament::bootCurrentPanel();

        // first every create form is filled and saved (twice round, for forms that need another
        // resource's record, e.g. an ad needs a zone); the records stay until the final rollback
        $resources = Filament::getPanel($panelId)->getResources();
        $failed = [];

        foreach ([1, 2] as $round) {
            $this->problems = [];

            foreach ($round === 1 ? $resources : $failed as $resource) {
                $pages = collect($resource::getPages())->map(fn ($registration) => $registration->getPage());
                // a create page, or a "manage" list whose Create button opens the form in a modal
                $create = $pages->first(fn ($page) => is_subclass_of($page, CreateRecord::class))
                    ?? $pages->first(fn ($page) => is_subclass_of($page, ManageRecords::class));

                if ($create && ! $this->createWithForm(class_basename($resource).' create (filled and saved)', $resource, $create)) {
                    $failed[$resource] = $resource;
                } else {
                    unset($failed[$resource]);
                }
            }
        }

        if ($afterCreating) {
            $afterCreating();
        }

        foreach (['en', 'ar'] as $locale) {
            app()->setLocale($locale);

            foreach ($resources as $resource) {
                $this->sweepResource($resource, $locale);
            }

            foreach (Filament::getPanel($panelId)->getPages() as $page) {
                $this->check("{$page} [{$locale}]", fn () => Livewire::test($page));
            }
        }

        if ($this->empty !== []) {
            fwrite(STDERR, "\nNo record to open (edit/view not checked): ".implode(', ', array_keys($this->empty))."\n");
        }

        $this->assertSame([], $this->problems, count($this->problems)." problem(s):\n\n".implode("\n\n", $this->problems));
    }

    /** @param  class-string<resource>  $resource */
    private function sweepResource(string $resource, string $locale): void
    {
        $name = class_basename($resource)." [{$locale}]";
        $pages = collect($resource::getPages())->map(fn ($registration) => $registration->getPage());

        $newest = $this->record($resource);
        $this->sweepPages($resource, $name, $pages, $newest);

        // older records too (written by older code or the seeders): all of a small table (25 rows), else the oldest
        $others = $resource::getEloquentQuery()->count() <= 25
            ? $resource::getEloquentQuery()->get()
            : collect([$this->record($resource, oldest: true)]);

        foreach ($others->filter()->reject(fn (Model $record) => $record->is($newest)) as $record) {
            // relations too: the newest record is often the one the sweep just made, with no rows under it
            $this->sweepPages($resource, "{$name} #{$record->getKey()}", $pages->only(['edit', 'view']), $record);
        }
    }

    /** @param  class-string<resource>  $resource */
    private function sweepPages(string $resource, string $name, $pages, ?Model $record, bool $relations = true): void
    {

        foreach ($pages as $key => $page) {
            $label = "{$name} {$key}";

            match (true) {
                is_subclass_of($page, ListRecords::class) => $this->check($label, fn () => Livewire::test($page)),
                is_subclass_of($page, CreateRecord::class) => $this->check($label, fn () => Livewire::test($page)),
                is_subclass_of($page, EditRecord::class) => $record
                    ? $this->check("{$label} (saved unchanged)", fn () => Livewire::test($page, ['record' => $record->getRouteKey()])->call('save')->assertHasNoFormErrors())
                    : null,
                is_subclass_of($page, ViewRecord::class) => $record
                    ? $this->check($label, fn () => Livewire::test($page, ['record' => $record->getRouteKey()]))
                    : null,
                default => $record ? $this->check($label, fn () => Livewire::test($page, ['record' => $record->getRouteKey()])) : null,
            };
        }

        $owner = $pages->first(fn ($page) => is_subclass_of($page, EditRecord::class) || is_subclass_of($page, ViewRecord::class));

        if (! $record) {
            $this->empty[class_basename($resource)] = true;
        }

        if (! $record || ! $owner || ! $relations) {
            return;
        }

        foreach ($resource::getRelations() as $manager) {
            foreach ($manager instanceof RelationGroup ? $manager->getManagers() : [$manager] as $one) {
                $one = is_string($one) ? $one : $one->relationManager;
                $this->check("{$name} relation ".class_basename($one), function () use ($one, $record, $owner) {
                    $test = Livewire::test($one, ['ownerRecord' => $record, 'pageClass' => $owner]);
                    $row = $test->instance()->getTable()->getRecords()->first();

                    // its first row saved unchanged through the row's Edit action, when it has one
                    if ($row && $test->instance()->getTable()->getAction('edit')) {
                        $test->mountTableAction('edit', $row)->callMountedTableAction()->assertHasNoTableActionErrors();
                    }

                    return $test;
                });
            }
        }
    }

    /**
     * The screens with no create form (logs, inboxes, what the app or a webhook sends), plus a YouTube
     * channel, whose form asks YouTube itself: one row each, so their pages can be opened.
     */
    private function seedReadOnlyRecords(): void
    {
        $now = now();
        $channel = DB::table('youtube_channels')->insertGetId(['channel_id' => 'UCsweep', 'title' => 'Sweep channel', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('youtube_channel_videos')->insert(['youtube_channel_id' => $channel, 'youtube_id' => 'dQw4w9WgXcQ', 'title' => 'Sweep video', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('content_reports')->insert(['section' => 'horses-for-sale', 'post_id' => (int) DB::table('horse_sale_posts')->max('id'), 'reason' => 'spam', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('notification_logs')->insert(['channel' => 'email', 'type' => 'order_received', 'recipient' => 'sweep@example.com', 'status' => 'sent', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('payment_gateway_logs')->insert(['gateway' => 'thawani', 'event' => 'session_created', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('whatsapp_webhooks')->insert(['event' => 'messages.upsert', 'payload' => '{}', 'created_at' => $now, 'updated_at' => $now]);

        if ($instance = DB::table('whatsapp_instances')->value('id')) {
            DB::table('whatsapp_messages')->insert(['id' => (string) str()->uuid(), 'instance_id' => $instance, 'message_id' => 'sweep', 'remote_jid' => '96899887766@s.whatsapp.net', 'phone' => '96899887766', 'direction' => 'outgoing', 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    /**
     * Fills every visible required field with a plausible value (a few rounds, since a choice can
     * reveal more fields), then creates. Reports the fields it could not satisfy.
     *
     * @param  class-string<resource>  $resource
     */
    private function createWithForm(string $label, string $resource, string $page): ?Model
    {
        $model = $resource::getModel();
        $before = $model::query()->max((new $model)->getKeyName());

        try {
            $test = Livewire::test($page);
            $modal = is_subclass_of($page, ManageRecords::class);

            if ($modal) {
                $test->mountAction('create');
            }

            $schema = fn () => $test->instance()->getSchema($modal ? $test->instance()->getMountedActionSchemaName() : 'form');
            $done = [];

            // a fresh look at the form before each field: a choice (country) changes the next one's options
            $required = function () use ($schema, &$done): ?Field {
                return collect($schema()->getFlatFields())
                    ->reject(fn (Field $field) => isset($done[$field->getStatePath()]) || $field->isDisabled() || $field instanceof Hidden)
                    ->first(fn (Field $field) => $field->isRequired());
            };

            for ($i = 0; $i < 60 && ($field = $required()); $i++) {
                $done[$field->getStatePath()] = true;
                $value = $this->sampleValue($field);

                if ($value !== null) {
                    $test->set($field->getStatePath(), $value);
                }
            }

            // "confirm" fields are required only with their pair: same value
            foreach ($schema()->getFlatFields() as $field) {
                if (str_ends_with($field->getStatePath(), '_confirmation')) {
                    $test->set($field->getStatePath(), data_get($test->instance(), str_replace('_confirmation', '', $field->getStatePath())));
                }
            }

            $modal ? $test->callMountedAction() : $test->call('create');
            $errors = $test->errors()->toArray();

            if ($errors !== []) {
                $this->problems[] = "{$label}: could not save: ".collect($errors)->map(fn ($messages, $key) => "{$key}: ".implode(' ', $messages))->implode('; ');

                return null;
            }

            return $model::query()->where((new $model)->getKeyName(), '>', $before ?? 0)->latest((new $model)->getKeyName())->first();
        } catch (Throwable $e) {
            $this->report($label, $e);

            return null;
        }
    }

    private function sampleValue(Field $field): mixed
    {
        $unique = substr(uniqid(), -6);
        $name = $field->getName();

        return match (true) {
            str_contains($name, 'youtube') => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            $name === 'svg' => '<path d="M0 0h10v10H0z"/>',
            $field instanceof TextInput && $field->isEmail() => "sweep{$unique}@example.com",
            $field instanceof TextInput && $field->isUrl() => 'https://example.com/'.$unique,
            $field instanceof TextInput && $field->isTel() => '9988'.rand(1000, 9999),
            $field instanceof TextInput && $field->isNumeric() => max(1, (float) ($field->getMinValue() ?? 1)),
            $field instanceof TextInput => str("sweep-{$unique}")->limit(max(3, (int) ($field->getMaxLength() ?? 40)), '')->toString(),
            $field instanceof Textarea, $field instanceof RichEditor, $field instanceof MarkdownEditor => 'Sweep text '.$unique,
            $field instanceof ColorPicker => '#336699',
            $field instanceof TimePicker => '09:00',
            // DatePicker extends DateTimePicker: the plain date first
            $field instanceof DatePicker => $field->getMinDate() ? substr($field->getMinDate(), 0, 10) : ($field->getMaxDate() ? now()->subYear()->format('Y-m-d') : now()->addDay()->format('Y-m-d')),
            $field instanceof DateTimePicker => ($field->getMinDate() ? substr($field->getMinDate(), 0, 10) : now()->addDay()->format('Y-m-d')).' 10:00:00',
            $field instanceof Toggle, $field instanceof Checkbox => true,
            $field instanceof CheckboxList, $field instanceof TagsInput => $this->firstOption($field, multiple: true),
            $field instanceof Select, $field instanceof Radio, $field instanceof ToggleButtons => $this->firstOption($field, multiple: method_exists($field, 'isMultiple') && $field->isMultiple()),
            $field instanceof FileUpload => $field->isMultiple() ? [UploadedFile::fake()->image('sweep.jpg')] : UploadedFile::fake()->image('sweep.jpg'),
            default => null,
        };
    }

    private function firstOption(Field $field, bool $multiple): mixed
    {
        $options = method_exists($field, 'getOptions') ? $field->getOptions() : [];

        if ($options === [] && $field instanceof Select && $field->isSearchable()) {
            $options = $field->getSearchResults('');
        }

        // grouped options: the first group's choices; a country list starts with Oman (the seeded regions are Omani)
        $flat = collect($options)->mapWithKeys(fn ($label, $key) => is_array($label) ? $label : [$key => $label]);
        $first = $flat->search(fn ($label) => in_array(strip_tags((string) $label), ['Oman', 'عمان', 'سلطنة عمان'], true)) ?: $flat->keys()->first();

        // a destination (to_city_id …) must differ from the origin: the last choice
        if (str_starts_with($field->getName(), 'to_') && str_ends_with($field->getName(), 'city_id')) {
            $first = $flat->keys()->last();
        }

        if ($first === null) {
            return $field instanceof TagsInput ? ['sweep'] : null;
        }

        return $multiple ? [$first] : $first;
    }

    /** @param  class-string<resource>  $resource */
    private function record(string $resource, bool $oldest = false): ?Model
    {
        try {
            $key = $resource::getModel()::make()->getKeyName();

            return $resource::getEloquentQuery()->reorder()->orderBy($key, $oldest ? 'asc' : 'desc')->first();
        } catch (Throwable $e) {
            $this->problems[] = class_basename($resource)." query: {$e->getMessage()}";

            return null;
        }
    }

    private function check(string $label, callable $open): void
    {
        DB::beginTransaction();

        try {
            $test = $open();
            $test->assertOk();
            $this->untranslated($label, $test->html());

            if (app()->getLocale() === 'ar') {
                $this->englishLabels($label, $test->instance());
            }
        } catch (Throwable $e) {
            $this->report($label, $e);
        } finally {
            DB::rollBack();
        }
    }

    private function report(string $label, Throwable $e): void
    {
        $where = collect($e->getTrace())->first(fn ($frame) => str_starts_with($frame['file'] ?? '', base_path('app')));
        $this->problems[] = "{$label}: ".get_class($e).': '.str($e->getMessage())->limit(600)
            .($where ? ' @ '.str_replace(base_path().'/', '', $where['file']).':'.$where['line'] : '');
    }

    /**
     * In the Arabic admin, a form field or table column whose label is plain English: usually one
     * without `->label()`, so Filament made one from its name ("Is active", "Created at").
     */
    private function englishLabels(string $label, object $page): void
    {
        $labels = [];

        if (! $page instanceof ListRecords && method_exists($page, 'getSchema') && ($form = $page->getSchema('form'))) {
            foreach ($form->getFlatFields() as $field) {
                if (! $field->isLabelHidden()) {
                    $labels[] = $field->getLabel();
                }
            }
        }

        if ($page instanceof ListRecords) {
            foreach ($page->getTable()->getColumns() as $column) {
                $labels[] = $column->getLabel();
            }
        }

        foreach (array_unique(array_filter(array_map(fn ($text) => trim(strip_tags((string) $text)), $labels))) as $text) {
            // product names and codes stay Latin (SMTP, URL, WhatsApp…)
            if (preg_match('/^[A-Za-z][A-Za-z ()\/.&-]{2,}$/', $text) && ! preg_match('/^(SMTP|TLS|SSL|URL|SVG|ID|SMS|VAT|API|PDF|CSV|QR|OTP|SEO|WhatsApp|YouTube|Instagram|Facebook|TikTok|Thawani|OMPAY|NBO|CCAvenue|Google|Apple|iOS|Android|Expo|Microchip|UELN)( [A-Za-z]+)?$/', $text)) {
                $this->problems[] = "{$label}: English label `{$text}`";
            }
        }
    }

    /** A translation key printed as is (missing in this language's file and the fallback). */
    private function untranslated(string $label, string $html): void
    {
        $text = html_entity_decode(strip_tags(preg_replace('#<(script|style)\b.*?</\1>#s', '', $html)));

        preg_match_all('/\b[a-z][a-z0-9_-]*\.(?:fields|actions|columns|sections|tabs|navigation|filters|notifications|labels|placeholders|helpers|options|statuses|types|messages)\.[a-z0-9_.]+\b/', $text, $matches);

        foreach (array_unique($matches[0]) as $key) {
            $this->problems[] = "{$label}: untranslated `{$key}`";
        }
    }
}
