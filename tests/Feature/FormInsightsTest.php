<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Filament\Forms\ViewFormInsights;
use App\Models\Country;
use App\Models\Service;
use App\Services\Forms\FormChart;
use App\Services\Forms\FormInsights;
use App\Services\Forms\FormInsightsExport;
use App\Services\Orders\CreateServiceOrder;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Packstub\FormBuilder\Filament\Resources\FormResource\Pages\EditForm;
use Packstub\FormBuilder\Filament\Resources\FormResource\Pages\ListForms;
use Packstub\FormBuilder\FormBuilder;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Models\FormSubmission;
use Tests\Concerns\MakesOrderForms;
use Tests\TestCase;

/** The per-form Insights page: what is counted, how it is charted, the page, the money and the downloads. */
class FormInsightsTest extends TestCase
{
    use MakesOrderForms;

    /** @var array<int, string> abilities the signed-in admin is refused (a part of the name is enough) */
    private array $denied = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareFormSite();
        Carbon::setTestNow('2026-09-24 12:00:00');

        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('en_name')->nullable();
            $table->string('ar_name')->nullable();
            $table->boolean('is_public')->default(true);
            $table->string('country_code')->nullable();
            $table->string('phone_code')->nullable();
            $table->string('currency_code')->nullable();
            $table->string('nationality_en')->nullable();
            $table->string('nationality_ar')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
        });

        Country::query()->insert([
            ['en_name' => 'Oman', 'ar_name' => 'عمان', 'country_code' => 'OM', 'nationality_en' => 'Omani', 'nationality_ar' => 'عماني', 'is_public' => true, 'order' => 1],
            ['en_name' => 'Bahrain', 'ar_name' => 'البحرين', 'country_code' => 'BH', 'nationality_en' => 'Bahraini', 'nationality_ar' => 'بحريني', 'is_public' => true, 'order' => 2],
        ]);

        Gate::before(fn ($user, string $ability) => ! collect($this->denied)->contains(fn (string $d) => str_contains($ability, $d)));
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function admin(): Authenticatable
    {
        return new class(['name' => 'Admin', 'email' => 'admin@example.com']) extends Authenticatable
        {
            protected $guarded = [];
        };
    }

    private function choices(array $values): array
    {
        return ['choices' => array_map(fn (string $v) => ['value' => strtolower($v), 'label' => ['en' => $v, 'ar' => $v.' (ar)']], $values)];
    }

    private function surveyForm(?Service $service = null): Form
    {
        return Form::create([
            'name' => ['en' => 'Owner survey', 'ar' => 'استبيان الملاك'],
            'slug' => 'owner-survey',
            'fields' => [
                $this->fieldItem('text', 'full_name', 'Full name'),
                $this->fieldItem('select', 'colour', 'Coat colour', false, $this->choices(['Bay', 'Grey', 'Black'])),
                $this->fieldItem('checkboxes', 'services', 'Services used', false, $this->choices(['Clinic', 'Farrier', 'Transport'])),
                $this->fieldItem('checkbox', 'agree', 'Agrees to contact', false),
                $this->fieldItem('conditional_radio', 'travelled', 'Travelled abroad', false, $this->choices(['Yes', 'No']) + ['reveal_on' => ['yes']]),
                $this->fieldItem('nationality', 'nationality', 'Nationality', false),
                $this->fieldItem('number', 'horses', 'Horses owned', false),
                $this->fieldItem('date', 'visit', 'Preferred visit', false),
                $this->fieldItem('textarea', 'notes', 'Notes', false),
            ],
            'settings' => ['min_seconds' => 0, 'payment' => ['service_id' => $service?->getKey()]],
        ]);
    }

    private function submit(Form $form, array $data, string $at = '2026-09-20 10:00:00'): FormSubmission
    {
        return FormSubmission::query()->create(['form_id' => $form->id, 'data' => $data, 'created_at' => $at, 'updated_at' => $at]);
    }

    private function seedAnswers(Form $form): void
    {
        $this->submit($form, ['full_name' => 'A', 'colour' => 'bay', 'services' => ['clinic', 'farrier'], 'agree' => true, 'travelled' => ['answer' => 'yes', 'details' => 'Italy'], 'nationality' => 'Omani', 'horses' => 2, 'visit' => '2026-10-03', 'notes' => 'secret text']);
        $this->submit($form, ['full_name' => 'B', 'colour' => 'bay', 'services' => ['clinic'], 'agree' => false, 'travelled' => ['answer' => 'no'], 'nationality' => 'عماني', 'horses' => 5, 'visit' => '2026-11-12']);
        $this->submit($form, ['full_name' => 'C', 'colour' => 'chestnut', 'services' => [], 'agree' => true, 'nationality' => 'Bahraini', 'horses' => 3, 'visit' => '2026-10-20']);
        // last month: outside "this month"
        $this->submit($form, ['full_name' => 'D', 'colour' => 'grey'], '2026-08-15 10:00:00');
    }

    // ── Counting ─────────────────────────────────────────────────────────────

    public function test_choices_are_counted_in_form_order_with_zeros_and_answers_no_longer_offered(): void
    {
        $form = $this->surveyForm();
        $this->seedAnswers($form);

        $colour = FormInsights::forPeriod($form, 'all')->fieldResult('colour');

        $this->assertSame('choices', $colour['kind']);
        $this->assertSame(4, $colour['answered']);
        $this->assertSame(['Bay', 'Grey', 'Black', 'chestnut'], array_column($colour['rows'], 'label'));
        $this->assertSame([2, 1, 0, 1], array_column($colour['rows'], 'count'));
        $this->assertSame(50.0, $colour['rows'][0]['share']);
    }

    public function test_every_kind_of_field_is_counted_and_free_text_never_is(): void
    {
        $form = $this->surveyForm();
        $this->seedAnswers($form);

        $insights = FormInsights::forPeriod($form, 'this_month');
        $results = $insights->fieldResults();

        $this->assertSame(3, $insights->total());
        $this->assertSame(['colour', 'services', 'agree', 'travelled', 'nationality', 'horses', 'visit'], $results->keys()->all());

        // several answers: shares are of those who picked anything, so they can add up past 100
        $services = $results['services'];
        $this->assertSame(2, $services['answered']);
        $this->assertSame([2, 1, 0], array_column($services['rows'], 'count'));
        $this->assertSame([100.0, 50.0, 0.0], array_column($services['rows'], 'share'));

        $this->assertSame([2, 1], array_column($results['agree']['rows'], 'count'));
        $this->assertSame([1, 1], array_column($results['travelled']['rows'], 'count'));

        // the same nationality typed in English or Arabic is one country
        $nationality = $results['nationality'];
        $this->assertSame(['Omani', 'Bahraini'], array_column($nationality['rows'], 'label'));
        $this->assertSame([2, 1], array_column($nationality['rows'], 'count'));
        $this->assertSame(['OM', 'BH'], array_column($nationality['rows'], 'code'));

        $horses = $results['horses'];
        $this->assertSame(['2', '3', '4', '5'], array_column($horses['rows'], 'label'));
        $this->assertSame([1, 1, 0, 1], array_column($horses['rows'], 'count'));
        $this->assertEquals(['min' => 2, 'max' => 5, 'mean' => 3.33, 'median' => 3], $horses['stats']);

        $this->assertSame([2, 1], array_column($results['visit']['rows'], 'count'));

        $this->assertStringNotContainsString('secret text', json_encode($results->all()));
    }

    public function test_numbers_with_many_values_are_grouped_into_round_ranges(): void
    {
        $form = $this->surveyForm();

        foreach ([1, 12, 25, 38, 99] as $n) {
            $this->submit($form, ['horses' => $n]);
        }

        $rows = FormInsights::forPeriod($form, 'all')->fieldResult('horses')['rows'];

        $this->assertSame('0–10', $rows[0]['label']);
        $this->assertSame('90–100', end($rows)['label']);
        $this->assertSame(5, array_sum(array_column($rows, 'count')));
    }

    public function test_the_period_and_order_status_filters_narrow_the_submissions(): void
    {
        $service = $this->makeService(10);
        $form = $this->surveyForm($service);
        $this->seedAnswers($form);

        $this->assertSame(4, FormInsights::forPeriod($form, 'all')->total());
        $this->assertSame(1, FormInsights::forPeriod($form, 'last_month')->total());
        $this->assertSame(3, FormInsights::forPeriod($form, 'custom', '2026-09-01', '2026-09-30')->total());
        $this->assertSame(3, FormInsights::forPeriod($form, 'this_month')->previous()->total() + 2);

        $submission = FormSubmission::query()->where('form_id', $form->id)->first();
        $order = app(CreateServiceOrder::class)->handle($service, ['name' => 'Ali', 'phone' => '96891234567'], formId: $form->id, submissionId: $submission->id);
        $order->forceFill(['status' => OrderStatus::Completed])->save();

        $completed = FormInsights::forPeriod($form, 'all', orderStatus: 'completed');
        $this->assertSame(1, $completed->total());
        $this->assertSame(1, collect($completed->ordersByStatus())->firstWhere('key', 'completed')['count']);
        $this->assertSame(0, FormInsights::forPeriod($form, 'all', orderStatus: 'rejected')->total());
    }

    public function test_the_cached_counts_follow_new_submissions(): void
    {
        $form = $this->surveyForm();
        $this->submit($form, ['colour' => 'bay']);

        $this->assertSame(1, FormInsights::forPeriod($form, 'all')->total());

        $this->submit($form, ['colour' => 'grey']);

        $this->assertSame(2, FormInsights::forPeriod($form, 'all')->total());
    }

    public function test_labels_follow_the_language_while_counts_stay_the_same(): void
    {
        $form = $this->surveyForm();
        $this->seedAnswers($form);

        app()->setLocale('ar');
        $insights = FormInsights::forPeriod($form, 'all');

        $this->assertSame('Bay (ar)', $insights->fieldResult('colour')['rows'][0]['label']);
        $this->assertSame('عماني', $insights->fieldResult('nationality')['rows'][0]['label']);
    }

    // ── Charts ───────────────────────────────────────────────────────────────

    public function test_each_kind_of_answer_gets_its_own_chart_form(): void
    {
        $form = $this->surveyForm();
        $this->seedAnswers($form);
        $results = FormInsights::forPeriod($form, 'all')->fieldResults();

        $this->assertSame('bar', FormChart::forField($results['colour'])['type']);
        $this->assertSame('donut', FormChart::forField($results['colour'], 'donut')['type']);
        // several answers per person are not parts of a whole: never a donut
        $this->assertSame('bar', FormChart::forField($results['services'], 'donut')['type']);
        $this->assertSame('meter', FormChart::forField($results['agree'])['type']);
        $this->assertSame('column', FormChart::forField($results['horses'])['type']);
        $this->assertSame('map', FormChart::forField($results['nationality'], map: true)['type']);
        $this->assertSame(['OM', 'BH'], FormChart::forField($results['nationality'], map: true)['codes']);
        $this->assertSame('percent', FormChart::forField($results['colour'], percent: true)['format']);
    }

    public function test_a_donut_with_too_many_slices_becomes_a_bar_and_a_long_tail_folds_into_other(): void
    {
        $rows = array_map(fn (int $i) => ['key' => "c{$i}", 'label' => "C{$i}", 'count' => 10 - $i, 'share' => 0.0], range(0, 7));
        $result = ['kind' => 'choices', 'label' => 'Many', 'answered' => 50, 'rows' => $rows];

        $this->assertSame('bar', FormChart::forField($result, 'donut')['type']);

        $top = FormChart::top($rows, 3, 52);
        $this->assertCount(4, $top);
        $this->assertSame(__('form_charts.other'), $top[3]['label']);
        $this->assertSame(7 + 6 + 5 + 4 + 3, $top[3]['count']);
    }

    // ── The page ─────────────────────────────────────────────────────────────

    public function test_the_page_shows_the_charts_and_their_tables(): void
    {
        $form = $this->surveyForm();
        $this->seedAnswers($form);

        Livewire::test(ViewFormInsights::class, ['record' => $form->getRouteKey()])
            ->assertSuccessful()
            ->assertSee(__('admin_form_insights.sections.over_time'))
            ->assertSee('Coat colour')
            ->assertSee('Services used')
            ->assertSee('data-form-chart', false)
            ->assertSee('Bahraini')
            ->assertDontSee('secret text')
            ->assertDontSee(__('admin_form_insights.sections.money'))
            ->fillForm(['period' => 'last_month'])
            ->assertSee(__('admin_form_insights.field.answered', ['answered' => 1, 'total' => 1, 'type' => __('admin_form_insights.kinds.choices')]));
    }

    public function test_a_form_without_chartable_fields_or_submissions_says_so(): void
    {
        $form = $this->makeForm();

        Livewire::test(ViewFormInsights::class, ['record' => $form->getRouteKey()])
            ->assertSee(__('admin_form_insights.empty'));

        $this->submit($form, ['full_name' => 'Ali']);

        Livewire::test(ViewFormInsights::class, ['record' => $form->getRouteKey()])
            ->assertSee(__('admin_form_insights.no_chartable'));
    }

    public function test_a_paid_form_shows_its_orders_and_money_to_those_who_see_the_income_report(): void
    {
        $service = $this->makeService(10);
        $form = $this->surveyForm($service);
        $submission = $this->submit($form, ['colour' => 'bay']);
        $order = app(CreateServiceOrder::class)->handle($service, ['name' => 'Ali', 'phone' => '96891234567'], formId: $form->id, submissionId: $submission->id);
        $order->forceFill(['payment_status' => PaymentStatus::Paid, 'payment_method' => PaymentGateway::Thawani, 'paid_at' => '2026-09-20 10:00:00'])->save();

        Livewire::test(ViewFormInsights::class, ['record' => $form->getRouteKey()])
            ->assertSee(__('admin_form_insights.sections.orders_by_status'))
            ->assertSee(__('admin_form_insights.sections.money'))
            ->assertSee('OMR 10.500');

        $this->denied = ['IncomeReport'];

        Livewire::test(ViewFormInsights::class, ['record' => $form->getRouteKey()])
            ->assertSee(__('admin_form_insights.sections.orders_by_status'))
            ->assertDontSee(__('admin_form_insights.sections.money'));
    }

    public function test_the_page_needs_permission_to_view_the_form(): void
    {
        $form = $this->surveyForm();

        $this->denied = ['view', 'View'];

        $this->assertFalse(ViewFormInsights::canAccess(['record' => $form]));
        Livewire::test(ViewFormInsights::class, ['record' => $form->getRouteKey()])->assertForbidden();
    }

    public function test_the_forms_list_and_the_editor_link_to_the_insights(): void
    {
        $form = $this->surveyForm();
        $url = ViewFormInsights::getUrl(['record' => $form]);

        $this->assertStringEndsWith('/admin/forms/'.$form->getRouteKey().'/insights', $url);

        Livewire::test(ListForms::class)->assertTableActionVisible('insights', $form);
        Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])->assertActionVisible('insights');
    }

    public function test_forgetting_the_hooks_removes_the_extra_pages_and_actions(): void
    {
        $builder = app(FormBuilder::class);
        $this->assertArrayHasKey('insights', $builder->resourcePages());
        $this->assertNotEmpty($builder->recordActions());

        $builder->forgetHooks();

        $this->assertSame([], $builder->resourcePages());
        $this->assertSame([], $builder->recordActions());
    }

    // ── Downloads ────────────────────────────────────────────────────────────

    public function test_the_csv_and_pdf_carry_the_same_numbers_in_the_chosen_language(): void
    {
        $form = $this->surveyForm();
        $this->seedAnswers($form);
        $insights = FormInsights::forPeriod($form, 'all');
        $export = app(FormInsightsExport::class);

        $stream = fopen('php://memory', 'w+');
        $export->csv($stream, $insights, 'ar', false);
        rewind($stream);
        $csv = stream_get_contents($stream);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('إحصاءات النموذج', $csv);
        $this->assertStringContainsString('"Bay (ar)",2,50%', $csv);
        $this->assertStringContainsString('عماني,2', $csv);
        $this->assertStringNotContainsString('secret text', $csv);
        $this->assertSame('en', app()->getLocale());

        $html = view('pdf.form-insights', [
            ...$export->data($insights, false),
            'moneyLines' => [], 'currency' => 'OMR', 'currencyIcon' => null, 'logo' => null, 'siteName' => 'Site',
            'locale' => 'en', 'rtl' => false, 'd' => fn (?string $t) => $t,
        ])->render();
        $this->assertStringContainsString('Coat colour', $html);
        $this->assertStringContainsString('class="meter"', $html);

        $this->assertStringStartsWith('%PDF', $export->pdf($insights, 'en', false));
        $this->assertSame('form-insights-owner-survey-2026-08-15_2026-09-24.csv', $export->filename($insights, 'csv'));
    }
}
