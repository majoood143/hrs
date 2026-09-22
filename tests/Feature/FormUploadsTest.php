<?php

namespace Tests\Feature;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Packstub\FormBuilder\Filament\SubmissionsCsv;
use Packstub\FormBuilder\Models\FormSubmission;
use Packstub\FormBuilder\Support\UploadLinks;
use Tests\Concerns\MakesOrderForms;
use Tests\TestCase;

/**
 * Uploaded files are private: a private disk, and a signed link that only someone allowed to see
 * the form's submissions can use.
 */
class FormUploadsTest extends TestCase
{
    use MakesOrderForms;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareFormSite();
        Storage::fake('local');
        Storage::fake('public');
    }

    private function upload(): FormSubmission
    {
        $this->makeForm(null, withFile: true);

        $this->post(route('packstub-form-builder.submit', 'passport'), $this->customerInput() + [
            'passport_copy' => UploadedFile::fake()->createWithContent('passport.pdf', '%PDF-1.4 fake passport'),
        ])->assertRedirect();

        return FormSubmission::firstOrFail();
    }

    private function admin(): void
    {
        Gate::before(fn () => true);
        $this->actingAs(new class(['name' => 'Admin']) extends Authenticatable
        {
            protected $guarded = [];
        });
    }

    public function test_an_upload_goes_to_the_private_disk_never_the_public_one(): void
    {
        $path = $this->upload()->value('passport_copy');

        $this->assertStringStartsWith('form-uploads/', $path);
        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_the_stored_link_is_signed_and_does_not_reveal_the_path(): void
    {
        $submission = $this->upload();
        $url = $submission->formatted()['passport_copy']['value'];

        $this->assertStringContainsString('/form-files/', $url);
        $this->assertStringContainsString('signature=', $url);
        $this->assertStringNotContainsString('form-uploads', $url);
        $this->assertStringNotContainsString('storage', $url);
    }

    public function test_a_guest_cannot_download_even_with_a_valid_link(): void
    {
        $url = $this->upload()->formatted()['passport_copy']['value'];

        $this->get($url)->assertForbidden();
    }

    public function test_an_unsigned_or_altered_link_is_refused(): void
    {
        $path = $this->upload()->value('passport_copy');
        $this->admin();

        $this->get(route(UploadLinks::ROUTE, ['token' => UploadLinks::encode($path)]))->assertForbidden();

        $signed = URL::signedRoute(UploadLinks::ROUTE, ['token' => UploadLinks::encode($path)]);
        $this->get(str_replace(UploadLinks::encode($path), UploadLinks::encode('form-uploads/other.pdf'), $signed))->assertForbidden();
    }

    public function test_an_allowed_user_downloads_it_as_an_attachment(): void
    {
        $submission = $this->upload();
        $url = $submission->formatted()['passport_copy']['value'];
        $this->admin();

        $response = $this->get($url)->assertOk();

        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));
        $this->assertSame('%PDF-1.4 fake passport', $response->streamedContent());
    }

    public function test_a_user_who_may_not_see_submissions_is_refused(): void
    {
        $url = $this->upload()->formatted()['passport_copy']['value'];

        $this->actingAs(new class(['name' => 'Nobody']) extends Authenticatable
        {
            protected $guarded = [];
        });
        Gate::define('viewAny', fn () => false);

        $this->get($url)->assertForbidden();
    }

    public function test_paths_outside_the_uploads_directory_never_resolve(): void
    {
        $this->admin();
        Storage::disk('local')->put('secrets/.env', 'APP_KEY=base64:secret');
        Storage::disk('local')->put('form-uploads/ok.pdf', 'fine');

        foreach (['secrets/.env', '../.env', 'form-uploads/../secrets/.env', 'form-uploads/', '/etc/passwd', 'form-uploads\\..\\x'] as $path) {
            $this->assertNull(UploadLinks::resolve(UploadLinks::encode($path)), $path);
            $this->get(URL::signedRoute(UploadLinks::ROUTE, ['token' => UploadLinks::encode($path)]))->assertNotFound();
        }

        $this->assertNull(UploadLinks::resolve('not base64 !!'));
        $this->assertSame('form-uploads/ok.pdf', UploadLinks::resolve(UploadLinks::encode('form-uploads/ok.pdf')));
        $this->get(URL::signedRoute(UploadLinks::ROUTE, ['token' => UploadLinks::encode('form-uploads/ok.pdf')]))->assertOk();
    }

    public function test_a_missing_file_is_a_404(): void
    {
        $this->admin();

        $this->get(URL::signedRoute(UploadLinks::ROUTE, ['token' => UploadLinks::encode('form-uploads/gone.pdf')]))->assertNotFound();
    }

    public function test_files_uploaded_before_the_change_are_still_served_from_the_old_public_disk(): void
    {
        $this->admin();
        Storage::disk('public')->put('form-uploads/old.pdf', 'old file');

        $response = $this->get(URL::signedRoute(UploadLinks::ROUTE, ['token' => UploadLinks::encode('form-uploads/old.pdf')]))->assertOk();

        $this->assertSame('old file', $response->streamedContent());
    }

    public function test_the_move_command_makes_old_uploads_private(): void
    {
        Storage::disk('public')->put('form-uploads/a.pdf', 'aaa');
        Storage::disk('public')->put('form-uploads/2026/b.pdf', 'bbbb');
        Storage::disk('public')->put('other/keep.txt', 'not ours');

        $this->artisan('form-builder:move-uploads-private', ['--dry-run' => true])->expectsOutputToContain('Would move 2 file(s)')->assertSuccessful();
        Storage::disk('public')->assertExists('form-uploads/a.pdf');
        Storage::disk('local')->assertMissing('form-uploads/a.pdf');

        $this->artisan('form-builder:move-uploads-private')->expectsOutputToContain('Moved 2 file(s)')->assertSuccessful();

        Storage::disk('local')->assertExists('form-uploads/a.pdf');
        Storage::disk('local')->assertExists('form-uploads/2026/b.pdf');
        $this->assertSame('bbbb', Storage::disk('local')->get('form-uploads/2026/b.pdf'));
        Storage::disk('public')->assertMissing('form-uploads/a.pdf');
        Storage::disk('public')->assertMissing('form-uploads/2026/b.pdf');
        Storage::disk('public')->assertExists('other/keep.txt');
    }

    public function test_the_csv_export_carries_the_signed_link(): void
    {
        $submission = $this->upload();

        ob_start();
        SubmissionsCsv::write($submission->form, collect([$submission]));
        $csv = ob_get_clean();

        $this->assertStringContainsString('/form-files/', $csv);
        $this->assertStringNotContainsString('form-uploads', $csv);
    }
}
