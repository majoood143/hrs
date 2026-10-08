<?php

namespace Tests\Feature;

use App\Filament\Forms\ViewFormInsights;
use App\Services\Exports\ExportDocument;
use App\Services\Exports\ExportPdf;
use App\Services\Forms\SubmissionsPdf;
use App\Support\Locale;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Packstub\FormBuilder\Filament\Resources\FormResource;
use Packstub\FormBuilder\Filament\Resources\FormResource\Pages\EditForm;
use Packstub\FormBuilder\Filament\Resources\FormResource\Pages\ListForms;
use Packstub\FormBuilder\Filament\Resources\FormResource\Pages\ManageSubmissions;
use Packstub\FormBuilder\FormBuilder;
use Packstub\FormBuilder\Models\Form;
use Packstub\FormBuilder\Models\FormSubmission;
use Tests\Concerns\MakesOrderForms;
use Tests\TestCase;

/** A form's submissions on their own page (a tab next to the editor), and their PDF in each language. */
class FormSubmissionsPageTest extends TestCase
{
    use MakesOrderForms;

    /** @var array<int, string> abilities the signed-in admin is refused (exact names) */
    private array $denied = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareFormSite();
        Carbon::setTestNow('2026-10-07 12:00:00');

        Gate::before(fn ($user, string $ability) => ! in_array($ability, $this->denied, true));
        $this->actingAs(new class(['name' => 'Admin']) extends Authenticatable
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

    private function surveyForm(): Form
    {
        return Form::create([
            'name' => ['en' => 'Owner survey', 'ar' => 'استبيان الملاك'],
            'slug' => 'owner-survey',
            'fields' => [
                ['type' => 'text', 'data' => ['key' => 'full_name', 'label' => ['en' => 'Full name', 'ar' => 'الاسم الكامل']]],
                ['type' => 'select', 'data' => ['key' => 'colour', 'label' => ['en' => 'Coat colour', 'ar' => 'لون الحصان'], 'choices' => [
                    ['value' => 'bay', 'label' => ['en' => 'Bay', 'ar' => 'أحمر']],
                    ['value' => 'grey', 'label' => ['en' => 'Grey', 'ar' => 'أشهب']],
                ]]],
                $this->fieldItem('file', 'passport_copy', 'Passport copy', false),
            ],
            'settings' => ['min_seconds' => 0],
        ]);
    }

    private function submit(Form $form, array $data, string $at = '2026-10-01 10:00:00'): FormSubmission
    {
        return FormSubmission::query()->create(['form_id' => $form->id, 'data' => $data, 'created_at' => $at, 'updated_at' => $at]);
    }

    public function test_the_submissions_page_lists_them_and_marks_one_read_when_opened(): void
    {
        $form = $this->surveyForm();
        $first = $this->submit($form, ['full_name' => 'Salim', 'colour' => 'bay']);
        $other = $this->submit($this->makeForm(slug: 'other'), ['full_name' => 'Not this form']);

        Livewire::test(ManageSubmissions::class, ['record' => $form->getRouteKey()])
            ->assertSuccessful()
            ->assertSee('Submissions: Owner survey')
            ->assertCanSeeTableRecords([$first])
            ->assertCanNotSeeTableRecords([$other])
            ->mountTableAction('view', $first)
            ->assertSee('Salim');

        $this->assertTrue($first->fresh()->isRead());
    }

    public function test_the_page_needs_permission_to_view_the_form(): void
    {
        $form = $this->surveyForm();
        $this->denied = ['view'];

        Livewire::test(ManageSubmissions::class, ['record' => $form->getRouteKey()])->assertForbidden();
        $this->assertNull(FormResource::submissionsUrl($form));
    }

    public function test_a_form_has_tabs_for_the_editor_its_submissions_and_insights(): void
    {
        $form = $this->surveyForm();
        $this->submit($form, ['full_name' => 'Unread one']);

        $items = Livewire::test(EditForm::class, ['record' => $form->getRouteKey()])->instance()->getSubNavigation();
        $labels = array_map(fn ($item) => $item->getLabel(), $items);

        $this->assertSame(['Edit form', 'Submissions', 'Insights'], $labels);
        $this->assertSame(ManageSubmissions::getUrl(['record' => $form]), $items[1]->getUrl());
        $this->assertSame('1', $items[1]->getBadge());
        $this->assertSame(ViewFormInsights::getUrl(['record' => $form]), $items[2]->getUrl());
    }

    public function test_the_forms_list_opens_a_forms_submissions(): void
    {
        $form = $this->surveyForm();

        Livewire::test(ListForms::class)
            ->assertTableActionVisible('submissions', $form)
            ->assertTableActionHasUrl('submissions', ManageSubmissions::getUrl(['record' => $form]), $form);
    }

    public function test_the_pdf_has_each_submissions_answers_in_the_chosen_language(): void
    {
        $form = $this->surveyForm();
        $this->submit($form, ['full_name' => 'Salim', 'colour' => 'bay', 'passport_copy' => 'form-uploads/abc/passport.pdf']);
        $this->submit($form, ['full_name' => 'Aisha', 'colour' => 'grey'], '2026-10-02 09:30:00');

        $pdf = app(SubmissionsPdf::class);
        $document = Locale::within('ar', fn () => $pdf->document($form, $form->submissions()->getQuery()->latest()));
        $rows = iterator_to_array($document->rows());

        $this->assertSame(ExportDocument::DETAILS, $document->layout);
        $this->assertSame('الإرسالات: استبيان الملاك', $document->title);
        $this->assertSame(['heading' => 'الإرسال رقم 2 · 2026-10-02 09:30'], $rows[0]);
        $this->assertContains(['label' => 'لون الحصان', 'value' => 'أشهب'], $rows);
        $this->assertContains(['label' => 'لون الحصان', 'value' => 'أحمر'], $rows);
        // a file by its name, never a (signed, expiring) link
        $this->assertContains(['label' => 'Passport copy', 'value' => 'passport.pdf'], $rows);
        $this->assertSame('en', app()->getLocale());

        // the same form read again in English: the form's fields are not stuck in the first language
        $english = iterator_to_array($pdf->document($form, $form->submissions()->getQuery())->rows());
        $this->assertContains(['label' => 'Coat colour', 'value' => 'Grey'], $english);

        $this->assertStringStartsWith('%PDF', app(ExportPdf::class)->render($document));
    }

    public function test_one_submission_gets_its_own_title_and_a_long_list_says_it_was_cut(): void
    {
        $form = $this->surveyForm();
        $submission = $this->submit($form, ['full_name' => 'Salim']);

        $one = app(SubmissionsPdf::class)->document($form, collect([$submission]));
        $this->assertSame('Submission #1', $one->title);
        $this->assertSame('Owner survey', $one->subtitle);
        $this->assertSame('2026-10-01 10:00', $one->facts['Submitted']);

        $many = app(SubmissionsPdf::class)->document($form, collect(array_fill(0, SubmissionsPdf::MAX_SUBMISSIONS + 5, $submission)));
        $this->assertStringStartsWith('the first 300 of 305', $many->facts['Records']);
    }

    public function test_the_page_downloads_pdfs_of_all_the_selected_or_one_submission(): void
    {
        $form = $this->surveyForm();
        $a = $this->submit($form, ['full_name' => 'Salim']);
        $b = $this->submit($form, ['full_name' => 'Aisha']);

        Livewire::test(ManageSubmissions::class, ['record' => $form->getRouteKey()])
            ->callTableAction('exportPdfAr')
            ->assertFileDownloaded('owner-survey-submissions-2026-10-07-ar.pdf');

        Livewire::test(ManageSubmissions::class, ['record' => $form->getRouteKey()])
            ->callTableAction('submissionPdfEn', $b)
            ->assertFileDownloaded('owner-survey-submission-'.$b->getKey().'-en.pdf');

        Livewire::test(ManageSubmissions::class, ['record' => $form->getRouteKey()])
            ->callTableBulkAction('selectedPdfEn', [$a, $b])
            ->assertFileDownloaded('owner-survey-submissions-2026-10-07-en.pdf');
    }

    public function test_the_actions_are_grouped_into_menus(): void
    {
        $form = $this->surveyForm();

        $table = Livewire::test(ManageSubmissions::class, ['record' => $form->getRouteKey()])->instance()->getTable();
        $this->assertContainsOnlyInstancesOf(ActionGroup::class, $table->getRecordActions());
        $this->assertCount(1, $table->getRecordActions());
        $this->assertCount(1, $table->getHeaderActions());
        $this->assertSame(
            ['export', 'exportPdfEn', 'exportPdfAr'],
            array_keys(array_values($table->getHeaderActions())[0]->getFlatActions()),
        );

        $forms = Livewire::test(ListForms::class)->instance()->getTable();
        $this->assertCount(1, $forms->getRecordActions());
        $this->assertContainsOnlyInstancesOf(ActionGroup::class, $forms->getRecordActions());
    }

    public function test_forgetting_the_hooks_removes_the_table_changes(): void
    {
        $builder = app(FormBuilder::class);
        $this->assertNotEmpty($builder->submissionActions($this->surveyForm(), new ListForms)['export']);

        $builder->forgetHooks();

        $this->assertSame(['export' => [], 'record' => [], 'bulk' => []], $builder->submissionActions(Form::query()->first(), new ListForms));
    }
}
