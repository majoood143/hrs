<?php

namespace Tests\Feature;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** Admin → Maintenance → Log viewer: "Download file" goes through a short-lived signed link. */
class AdminLogDownloadTest extends TestCase
{
    private string $file = 'zz-download-test.log';

    protected function tearDown(): void
    {
        @unlink(storage_path('logs/'.$this->file));

        parent::tearDown();
    }

    private function admin(): Authenticatable
    {
        return new class(['id' => 1, 'name' => 'Admin']) extends Authenticatable
        {
            protected $guarded = [];
        };
    }

    public function test_a_signed_link_downloads_the_log_file(): void
    {
        file_put_contents(storage_path('logs/'.$this->file), "[2026-10-18 10:00:00] local.INFO: hello\n");
        $url = URL::temporarySignedRoute('admin.logs.download', now()->addMinutes(5), ['file' => $this->file]);

        $this->actingAs($this->admin())->get($url)
            ->assertOk()
            ->assertDownload($this->file);
    }

    public function test_unsigned_links_and_other_files_are_refused(): void
    {
        file_put_contents(storage_path('logs/'.$this->file), 'x');

        $this->actingAs($this->admin())->get('/admin-logs/'.$this->file)->assertForbidden();

        $missing = URL::temporarySignedRoute('admin.logs.download', now()->addMinutes(5), ['file' => 'nope.log']);
        $this->actingAs($this->admin())->get($missing)->assertNotFound();
    }
}
