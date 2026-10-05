<?php

namespace Tests\Feature;

use App\Filament\Blocks\Cms\CmsBlocks;
use App\Models\Service;
use App\Support\FormChartBlock;
use Illuminate\Support\Carbon;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Models\FormSubmission;
use Tests\Concerns\MakesOrderForms;
use Tests\TestCase;

/** The public "Form results chart" CMS block: what it may show, when, and how it renders. */
class FormChartBlockTest extends TestCase
{
    use MakesOrderForms;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareFormSite();
        $this->withoutVite();
        Carbon::setTestNow('2026-09-24 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function poll(?Service $service = null, string $slug = 'favourite-breed'): Form
    {
        $choices = ['choices' => array_map(fn (string $v) => ['value' => strtolower($v), 'label' => ['en' => $v, 'ar' => $v.' (ar)']], ['Arabian', 'Thoroughbred', 'Shetland', 'Friesian'])];

        return Form::create([
            'name' => ['en' => 'Favourite breed', 'ar' => 'السلالة المفضلة'],
            'slug' => $slug,
            'fields' => [
                $this->fieldItem('text', 'full_name', 'Full name'),
                $this->fieldItem('radio', 'breed', 'Favourite breed', false, $choices),
                $this->fieldItem('email', 'email', 'Email', false),
            ],
            'settings' => ['min_seconds' => 0, 'payment' => ['service_id' => $service?->getKey()]],
        ]);
    }

    private function answer(Form $form, string $breed, int $times = 1, string $at = '2026-09-20 10:00:00'): void
    {
        for ($i = 0; $i < $times; $i++) {
            FormSubmission::query()->create(['form_id' => $form->id, 'data' => ['full_name' => 'Rider '.$i, 'breed' => $breed, 'email' => "r{$i}@example.com"], 'created_at' => $at, 'updated_at' => $at]);
        }
    }

    private function block(Form $form, array $extra = []): array
    {
        return $extra + ['form_id' => $form->id, 'field_key' => 'breed', 'heading' => ['en' => 'What riders like', 'ar' => 'ما يفضله الفرسان']];
    }

    public function test_paid_service_forms_and_free_text_fields_cannot_be_picked(): void
    {
        $poll = $this->poll();
        $paid = $this->poll($this->makeService(10), 'paid-form');

        $this->assertSame([$poll->id => 'Favourite breed'], FormChartBlock::formOptions());
        $this->assertSame(['breed' => 'Favourite breed'], FormChartBlock::fieldOptions($poll->id));
        $this->assertSame([], FormChartBlock::fieldOptions($paid->id));

        $this->answer($paid, 'arabian', 10);
        $this->assertNull(FormChartBlock::resolve($this->block($paid)));
        $this->assertNull(FormChartBlock::resolve($this->block($poll, ['field_key' => 'email'])));
        $this->assertNull(FormChartBlock::resolve($this->block($poll, ['form_id' => 999])));
    }

    public function test_nothing_is_shown_until_enough_people_answered(): void
    {
        $poll = $this->poll();
        $this->answer($poll, 'arabian', 4);

        $block = FormChartBlock::resolve($this->block($poll));
        $this->assertNull($block['spec']);
        $this->assertSame(5, $block['waiting']);

        // an admin may raise the bar, never lower it below five
        $this->assertSame(5, FormChartBlock::resolve($this->block($poll, ['min_answers' => 1]))['waiting']);
        $this->answer($poll, 'arabian');
        $this->assertNotNull(FormChartBlock::resolve($this->block($poll))['spec']);
        $this->assertNull(FormChartBlock::resolve($this->block($poll, ['min_answers' => 10]))['spec']);
    }

    public function test_rare_answers_are_folded_into_other(): void
    {
        $poll = $this->poll();
        $this->answer($poll, 'arabian', 6);
        $this->answer($poll, 'thoroughbred', 3);
        $this->answer($poll, 'shetland', 1);

        $spec = FormChartBlock::resolve($this->block($poll))['spec'];

        $this->assertSame(['Arabian', 'Thoroughbred', 'Friesian', __('form_charts.other')], $spec['labels']);
        $this->assertSame([6, 3, 0, 1], $spec['counts']);
        $this->assertNotContains('Shetland', $spec['labels']);
    }

    public function test_the_dates_style_and_display_are_applied(): void
    {
        $poll = $this->poll();
        $this->answer($poll, 'arabian', 5, '2026-08-10 10:00:00');
        $this->answer($poll, 'friesian', 5, '2026-09-10 10:00:00');

        $september = FormChartBlock::resolve($this->block($poll, ['date_from' => '2026-09-01', 'style' => 'donut', 'display' => 'percent']));

        $this->assertSame(5, $september['answered']);
        $this->assertSame('donut', $september['spec']['type']);
        $this->assertSame('percent', $september['spec']['format']);
        $this->assertSame(10, FormChartBlock::resolve($this->block($poll))['answered']);
        $this->assertSame(5, FormChartBlock::resolve($this->block($poll, ['date_to' => '2026-08-31']))['answered']);
    }

    public function test_the_block_renders_the_chart_with_its_table_in_the_visitors_language(): void
    {
        $poll = $this->poll();
        $this->answer($poll, 'arabian', 5);

        $html = view('cms.blocks.form_chart', ['data' => $this->block($poll)])->render();

        $this->assertStringContainsString('What riders like', $html);
        $this->assertStringContainsString('data-form-chart', $html);
        $this->assertStringContainsString(__('form_charts.show_table'), $html);
        $this->assertStringContainsString(__('form_charts.based_on', ['count' => 5]), $html);
        $this->assertStringNotContainsString('r1@example.com', $html);
        $this->assertStringNotContainsString('Rider 1', $html);

        app()->setLocale('ar');
        $arabic = view('cms.blocks.form_chart', ['data' => $this->block($poll, ['show_table' => false])])->render();

        $this->assertStringContainsString('ما يفضله الفرسان', $arabic);
        $this->assertStringContainsString('Arabian (ar)', $arabic);
        $this->assertStringContainsString('"dir":"rtl"', $arabic);
        $this->assertStringNotContainsString(__('form_charts.show_table'), $arabic);
    }

    public function test_the_block_waits_politely_and_vanishes_for_a_paid_form(): void
    {
        $poll = $this->poll();
        $this->answer($poll, 'arabian', 2);

        $html = view('cms.blocks.form_chart', ['data' => $this->block($poll)])->render();
        $this->assertStringContainsString(__('form_charts.waiting', ['count' => 5]), $html);
        $this->assertStringNotContainsString('data-form-chart', $html);

        $paid = $this->poll($this->makeService(10), 'paid-form');
        $this->assertSame('', trim(view('cms.blocks.form_chart', ['data' => $this->block($paid)])->render()));
    }

    public function test_the_block_is_offered_in_the_page_builder(): void
    {
        $names = array_map(fn ($block) => $block->getName(), CmsBlocks::all());

        $this->assertContains('form_chart', $names);
    }
}
