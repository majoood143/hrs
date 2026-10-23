<?php

namespace Tests\Feature;

use App\Services\Forms\FormInsights;
use App\Services\Forms\RacePredictions;
use App\Services\Forms\RaceWinnerField;
use Filament\Facades\Filament;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Packstub\FormBuilder\Fields\FieldTypeRegistry;
use Packstub\FormBuilder\Filament\Resources\FormResource\Pages\CreateForm;
use Packstub\FormBuilder\Filament\Resources\FormResource\Pages\EditForm;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Models\FormSubmission;
use Tests\Concerns\FakesRacingSource;
use Tests\Concerns\MakesOrderForms;
use Tests\TestCase;

/**
 * Race prediction forms: a race day from the racing calendar becomes Name, Mobile and one
 * "who wins?" dropdown per race, its runners copied from the race card (or the entries) and
 * valued by the source's horse id, then kept up to date until the form closes.
 *
 * The faked source's 17 Oct 2026 has eight races: the first (10005) has nothing declared yet,
 * the other seven share one 19-runner race card.
 */
class RacePredictionFormTest extends TestCase
{
    use FakesRacingSource;
    use MakesOrderForms;

    protected function setUp(): void
    {
        parent::setUp();

        // the form site already lays out the site; the racing source only needs its address
        $this->prepareFormSite();
        config(['racing.base_url' => 'http://racing.test']);
        $this->fakeRacingSource();
        Carbon::setTestNow('2026-10-10 08:00:00');

        Gate::before(fn () => true);
        $this->actingAs(new class(['name' => 'Admin', 'email' => 'admin@example.com']) extends Authenticatable
        {
            protected $guarded = [];
        });
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_a_race_day_becomes_name_mobile_and_one_prediction_per_race(): void
    {
        $form = app(RacePredictions::class)->form('2026-10-17');

        $this->assertSame('Race predictions · 17 October 2026', $form['name']['en']);
        $this->assertStringStartsWith('توقعات السباقات', $form['name']['ar']);
        // the first race starts at 13:30 in Muscat
        $this->assertSame('2026-10-17 09:30:00', $form['closes_at']);

        $data = array_column($form['fields'], 'data');
        $this->assertSame(['name', 'mobile', 'race_1', 'race_2', 'race_3', 'race_4', 'race_5', 'race_6', 'race_7', 'race_8'], array_column($data, 'key'));
        $this->assertSame(['text', 'phone', ...array_fill(0, 8, 'race_winner')], array_column($form['fields'], 'type'));

        $race2 = $data[3];
        $this->assertSame('Race 2 - 3YO&+ R48-NOR - 1600M', $race2['label']['en']);
        $this->assertSame('الشوط 2 - 3YO&+ R48-NOR - 1600M', $race2['label']['ar']);
        $this->assertSame(['2026-10-17', 10030, 'card'], [$race2['race_date'], $race2['race_id'], $race2['runners_from']]);
        $this->assertCount(19, $race2['choices']);
        $this->assertSame(['value' => '3125', 'label' => ['en' => 'S.s.r. Alfaseeh (OM)', 'ar' => 'S.s.r. Alfaseeh (OM)']], $race2['choices'][0]);
        // what the runner cards show besides the name, by horse id
        $this->assertSame('1', $race2['runners']['3125']['no']);
        $this->assertStringStartsWith('/img/OwnerColours/', $race2['runners']['3125']['image']);
        $this->assertNotEmpty($race2['runners']['3125']['jockey']['en']);

        // one entry per mobile number
        $this->assertTrue($data[1]['unique']);

        // nothing declared for race 1 yet: no runners, filled in later
        $this->assertSame([null, []], [$data[2]['runners_from'], $data[2]['choices']]);
    }

    public function test_the_new_form_page_starts_from_a_race_day(): void
    {
        Livewire::test(CreateForm::class)
            ->mountAction('racePredictions')
            ->assertSet('mountedActions.0.data.race_date', '2026-10-17')
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertSet('data.name.en', 'Race predictions · 17 October 2026')
            ->assertSet('data.slug', 'race-predictions-17-october-2026')
            ->assertCount('data.fields', 10)
            ->call('create')
            ->assertHasNoFormErrors();

        $form = Form::query()->where('slug', 'race-predictions-17-october-2026')->firstOrFail();
        $this->assertSame('2026-10-17 09:30:00', $form->closes_at->format('Y-m-d H:i:s'));
        $this->assertCount(19, $form->fieldList()->firstWhere('key', 'race_3')->choices());

        // race 1 has no runners yet: closed to visitors until it has
        $this->assertSame(__('race_predictions.waiting_for_runners'), $form->closedReason());
        $this->postJson(route('packstub-form-builder.submit', $form->slug), ['name' => 'Ali', 'mobile' => '9123 4567'])->assertStatus(403);

        $fields = $form->fields;
        $fields[2]['data']['choices'] = [['value' => '1', 'label' => ['en' => 'Declared', 'ar' => 'Declared']]];
        $form->update(['fields' => $fields]);
        $this->assertNull($form->fresh()->closedReason());
    }

    public function test_a_name_or_closing_time_already_typed_is_kept(): void
    {
        Livewire::test(CreateForm::class)
            ->set('data.name.en', 'Win a prize')
            ->set('data.closes_at', '2026-10-17 08:00:00')
            ->callAction('racePredictions', ['race_date' => '2026-10-17'])
            ->assertSet('data.name.en', 'Win a prize')
            ->assertSet('data.closes_at', '2026-10-17 08:00:00')
            ->assertCount('data.fields', 10);
    }

    public function test_visitors_see_the_race_card_header_and_a_card_per_runner(): void
    {
        $form = $this->makePredictionForm();
        $form->update(['fields' => [...$form->fields, $this->raceItem('race_1', 9128)]]);
        $this->assertSame('Dirt', $form->fresh()->fields[3]['data']['race_details']['en']['facts'][0]['value']);

        $html = Blade::render('<x-form-builder::form :form="$slug" />', ['slug' => 'predictions']);

        // the header, as on the race card: time, distance, breed, type, then surface / weather / rail, conditions, prizes
        foreach (['13:30', '1200M', 'Purebred Arabian', 'Handicap', 'Surface:</span> <bdi>Dirt</bdi>', 'Weather:</span> <bdi>Fine</bdi>', 'Rail Position:</span> <bdi>True</bdi>', '3YO&amp;+ R36-NOR (Local Bred)<br />', 'Prize money', '900.000'] as $text) {
            $this->assertStringContainsString($text, $html);
        }

        // the runners: radio cards with silks (through the site's racing image proxy), number, name, jockey, weight and gate
        $this->assertMatchesRegularExpression('/<input type="radio" class="rw-runner__input"[^>]+name="race_1"[^>]+value="3125"/s', $html);
        $this->assertStringContainsString(e(route('racing.image', ['p' => $form->fresh()->fields[3]['data']['runners']['3125']['image']])), $html);
        $this->assertStringContainsString('<span class="rw-runner__no">1</span>', $html);
        $this->assertStringContainsString('<bdi>S.s.r. Alfaseeh (OM)</bdi>', $html);
        $this->assertMatchesRegularExpression('/Jockey: [^<]+ · [\d.]+ kg · Gate \d+/', $html);
        $this->assertStringContainsString('Choose the winner', $html);
        // two race fields, one copy of the styles and the folding script
        $this->assertSame(1, substr_count($html, 'pick.open = false'));
        $this->assertSame(1, substr_count($html, '.rw-runner{'));

        app()->setLocale('ar');
        $html = Blade::render('<x-form-builder::form :form="$slug" />', ['slug' => 'predictions']);
        $this->assertStringContainsString('الشوط', $form->fresh()->fields[3]['data']['label']['ar']);
        $this->assertStringContainsString('اختر الفائز', $html);
        $this->assertStringContainsString('الفارس: ', $html);
        app()->setLocale('en');
    }

    public function test_one_entry_per_mobile_number_however_it_is_typed(): void
    {
        $form = $this->makePredictionForm();
        $fields = $form->fields;
        $fields[1]['data']['unique'] = true;
        $form->update(['fields' => $fields]);

        $send = fn (string $mobile) => $this->postJson(route('packstub-form-builder.submit', 'predictions'), ['name' => 'Ali', 'mobile' => $mobile, 'race_2' => '3125']);

        $send('9123 4567')->assertOk();
        $send('+968 9123-4567')->assertStatus(422)->assertJsonPath('errors.mobile.0', 'This Mobile has already been used to answer this form.');
        $send('٩١٢٣٤٥٦٧')->assertStatus(422);
        $send('9876 5432')->assertOk();

        $this->assertSame(2, FormSubmission::count());
    }

    public function test_visitors_pick_a_runner_by_name_and_the_horse_id_is_stored(): void
    {
        $this->makePredictionForm();

        $this->postJson(route('packstub-form-builder.submit', 'predictions'), ['name' => 'Ali', 'mobile' => '9123 4567', 'race_2' => 'ghost'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['race_2'], 'errors');

        $this->postJson(route('packstub-form-builder.submit', 'predictions'), ['name' => 'Ali', 'mobile' => '9123 4567', 'race_2' => '3125'])
            ->assertOk();

        $submission = FormSubmission::firstOrFail();
        $this->assertSame('3125', $submission->value('race_2'));
        $field = Form::firstOrFail()->fieldList()->firstWhere('key', 'race_2');
        $this->assertSame('S.s.r. Alfaseeh (OM)', $field->type->format('3125', $field));
    }

    public function test_reloading_follows_the_race_card_and_remembers_withdrawn_horses(): void
    {
        $form = $this->makePredictionForm(choices: [
            ['value' => '3125', 'label' => ['en' => 'Old label', 'ar' => 'Old label']],
            ['value' => '999', 'label' => ['en' => 'Scratched (OM)', 'ar' => 'منسحب (عُمان)']],
        ]);
        $form->update(['fields' => [
            ...$form->fields,
            // edited by hand, and a race already run: both left alone
            $this->raceItem('race_3', 10031, sync: false, kept: true),
            $this->raceItem('race_9', 10032, date: '2026-10-03', kept: true),
        ]]);

        $this->assertSame(1, app(RacePredictions::class)->reload($form->fresh()));

        $fields = Form::firstOrFail()->fieldList()->keyBy('key');
        $this->assertCount(19, $fields['race_2']->choices());
        $this->assertSame('S.s.r. Alfaseeh (OM)', $fields['race_2']->choices()['3125']);
        $this->assertArrayNotHasKey('999', $fields['race_2']->choices());
        $this->assertSame('Scratched (OM)', $fields['race_2']->type->format('999', $fields['race_2']));
        $this->assertCount(1, $fields['race_3']->choices());
        $this->assertCount(1, $fields['race_9']->choices());

        // nothing new: nothing saved
        $this->assertSame(0, app(RacePredictions::class)->reload(Form::firstOrFail()));
    }

    public function test_the_hourly_command_reloads_only_open_forms(): void
    {
        $open = $this->makePredictionForm(choices: []);
        $closed = $this->makePredictionForm(choices: [], slug: 'closed');
        $closed->update(['closes_at' => '2026-10-09 10:00:00']);

        $this->artisan('forms:reload-race-runners')->assertSuccessful();

        $this->assertCount(19, $open->fresh()->fieldList()->firstWhere('key', 'race_2')->choices());
        $this->assertCount(0, $closed->fresh()->fieldList()->firstWhere('key', 'race_2')->choices());
    }

    public function test_the_editor_keeps_the_runner_details_and_reloads_from_the_header(): void
    {
        $form = $this->makePredictionForm(choices: [['value' => '999', 'label' => ['en' => 'Scratched (OM)', 'ar' => 'منسحب']]]);

        Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
            ->assertSuccessful()
            ->assertSee(__('admin_race_predictions.editor.runners'))
            ->call('save')
            ->assertHasNoFormErrors();

        $data = $form->fresh()->fields[2]['data'];
        $this->assertSame(['2026-10-17', 10030, true, 'card'], [$data['race_date'], (int) $data['race_id'], (bool) $data['sync_runners'], $data['runners_from']]);

        $page = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])
            ->callAction('reloadRaceRunners')
            ->assertNotified(trans_choice('admin_race_predictions.reload.done', 1, ['count' => 1]));

        // the editor shows the reloaded runners straight away
        $this->assertCount(19, $page->get('data.fields.'.array_keys($page->get('data.fields'))[2].'.data.choices'));

        $this->assertSame('999', $form->fresh()->fields[2]['data']['withdrawn'][0]['value']);

        // a later save in the editor keeps each runner's card (the choices list itself holds only names)
        $page->call('save')->assertHasNoFormErrors();
        $data = $form->fresh()->fields[2]['data'];
        $this->assertCount(19, $data['runners']);
        $this->assertSame('1', $data['runners']['3125']['no']);
        $this->assertSame('999', $data['withdrawn'][0]['value']);
    }

    public function test_picking_a_race_in_the_editor_names_the_field_and_fills_its_runners(): void
    {
        $form = Form::create([
            'name' => ['en' => 'Predictions', 'ar' => 'توقعات'],
            'slug' => 'predictions',
            'fields' => [['type' => 'race_winner', 'data' => ['label' => ['en' => '', 'ar' => ''], 'required' => true]]],
            'settings' => ['min_seconds' => 0],
        ]);

        $page = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])->assertSuccessful();
        $path = 'data.fields.'.array_keys($page->get('data.fields'))[0].'.data';

        // a block just added has no key yet (saving the model above gave it the type's id)
        $page->set("{$path}.key", '')
            ->set("{$path}.race_date", '2026-10-17')
            ->set("{$path}.race_id", 10031)
            ->assertSet("{$path}.label.en", 'Race 3 - R70-NOR - 1600M')
            ->assertSet("{$path}.label.ar", 'الشوط 3 - R70-NOR - 1600M')
            ->assertSet("{$path}.key", 'race_3')
            ->assertSet("{$path}.runners_from", 'card')
            ->assertCount("{$path}.choices", 19)
            ->call('save')
            ->assertHasNoFormErrors();

        $field = $form->fresh()->fieldList()->first();
        $this->assertSame('race_3', $field->key);
        $this->assertSame('S.s.r. Alfaseeh (OM)', $field->choices()['3125']);
    }

    public function test_when_the_racing_site_is_down_nothing_is_changed(): void
    {
        $this->replaceRacingFake(fn () => Http::response('down', 500));

        // no race days to offer
        Livewire::test(CreateForm::class)
            ->callAction('racePredictions', ['race_date' => '2026-10-17'])
            ->assertHasActionErrors(['race_date'])
            ->assertCount('data.fields', 0);

        $form = $this->makePredictionForm(choices: []);
        $this->artisan('forms:reload-race-runners')->assertFailed();
        $this->assertSame([], $form->fresh()->fields[2]['data']['choices']);
    }

    public function test_insights_chart_the_picks_per_race_and_name_a_withdrawn_horse(): void
    {
        $form = $this->makePredictionForm(choices: [
            ['value' => '3125', 'label' => ['en' => 'S.s.r. Alfaseeh (OM)', 'ar' => 'S.s.r. Alfaseeh (OM)']],
            ['value' => '999', 'label' => ['en' => 'Scratched (OM)', 'ar' => 'منسحب (عُمان)']],
        ]);

        foreach (['91000001' => '3125', '91000002' => '3125', '91000003' => '999'] as $mobile => $pick) {
            $this->postJson(route('packstub-form-builder.submit', 'predictions'), ['name' => 'Ali', 'mobile' => (string) $mobile, 'race_2' => $pick])->assertOk();
        }

        $this->assertSame(['race_2'], FormInsights::forPeriod($form, 'all')->chartableFields()->keys()->all());

        // 999 is scratched afterwards: still charted, by its name
        app(RacePredictions::class)->reload($form->fresh());
        $rows = collect(FormInsights::forPeriod($form->fresh(), 'all')->fieldResult('race_2')['rows'])->keyBy('key');

        $this->assertSame(2, $rows['3125']['count']);
        $this->assertSame(66.7, $rows['3125']['share']);
        $this->assertSame('S.s.r. Alfaseeh (OM)', $rows['3125']['label']);
        $this->assertSame(['Scratched (OM)', 1], [$rows['999']['label'], $rows['999']['count']]);
        $this->assertSame(0, $rows['1515']['count']);
    }

    public function test_the_type_is_offered_in_the_choices_group_in_both_languages(): void
    {
        $type = app(FieldTypeRegistry::class)->get('race_winner');
        $this->assertInstanceOf(RaceWinnerField::class, $type);
        $this->assertSame('choices', $type->group());

        app()->setLocale('ar');
        $this->assertSame('توقع الفائز في السباق', $type->label());
    }

    private function makePredictionForm(?array $choices = null, string $slug = 'predictions'): Form
    {
        return Form::create([
            'name' => ['en' => 'Predictions', 'ar' => 'توقعات'],
            'slug' => $slug,
            'fields' => [
                $this->fieldItem('text', 'name', 'Name'),
                $this->fieldItem('phone', 'mobile', 'Mobile'),
                $this->raceItem('race_2', 10030, choices: $choices),
            ],
            'settings' => ['min_seconds' => 0],
        ]);
    }

    /** $kept: one hand-typed runner instead of the card's, to see that a reload leaves it alone. */
    private function raceItem(string $key, int $raceId, ?array $choices = null, bool $sync = true, string $date = '2026-10-17', bool $kept = false): array
    {
        $item = $choices === null
            ? app(RacePredictions::class)->field($date, ['id' => $raceId, 'number' => 2, 'time' => '14:00', 'distance' => 1600, 'name' => ['en' => 'R48', 'ar' => 'R48']])
            : $this->fieldItem('race_winner', $key, $key, true, ['race_date' => $date, 'race_id' => $raceId, 'sync_runners' => $sync, 'runners_from' => 'card', 'choices' => $choices]);

        $item['data']['key'] = $key;
        $item['data']['sync_runners'] = $sync;
        $item['data']['race_date'] = $date;

        if ($kept) {
            $item['data']['choices'] = [['value' => '1', 'label' => ['en' => 'Kept', 'ar' => 'Kept']]];
        }

        return $item;
    }
}
