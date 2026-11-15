<?php

namespace Tests\Feature\Api\V1;

use App\Support\MobileApp\MobilePages;
use Tests\Concerns\PreparesMobileApi;
use Tests\Concerns\PreparesMobileApiDirectory;
use Tests\TestCase;

/** Website pages shown in the app (admin → Mobile app → Pages): `app-status.pages`, `GET pages/{slug}`, the website's in-app mode. */
class PagesTest extends TestCase
{
    use PreparesMobileApi;
    use PreparesMobileApiDirectory;

    private int $files;

    private int $form;

    private int $draft;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareMobileApi();
        $this->prepareDirectoryTables();
        (require database_path('migrations/2026_10_13_120000_create_mobile_announcements_table.php'))->up();

        $this->files = $this->page('files', [
            ['type' => 'cta', 'data' => ['heading' => ['en' => 'Ownership form', 'ar' => 'استمارة الملكية'], 'button_text' => ['en' => 'Download'], 'button_url' => '/storage/9/form.pdf']],
            ['type' => 'divider', 'data' => []],
        ]);
        $this->form = $this->page('contact', [['type' => 'contact_form', 'data' => []]]);
        $this->draft = $this->page('draft', [], ['status' => 'draft']);
        $this->page('unlisted', []);
    }

    private function page(string $slug, array $blocks, array $values = []): int
    {
        return $this->row('cms_pages', $values + [
            'slug' => $slug, 'title' => $this->both(ucfirst($slug), 'صفحة '.$slug), 'excerpt' => $this->both('About '.$slug, 'عن '.$slug),
            'content' => json_encode($blocks), 'status' => 'published', 'published_at' => now()->subHour(),
        ]);
    }

    private function listPages(array $rows): void
    {
        $this->seedApiSettings(['mobile.pages' => json_encode($rows)]);
    }

    public function test_app_status_lists_the_admins_published_pages_in_order_and_language(): void
    {
        $this->getJson('/api/v1/app-status')->assertOk()->assertJsonPath('data.pages', []);

        $this->listPages([
            ['page_id' => $this->form, 'placement' => 'services'],
            ['page_id' => $this->draft, 'placement' => 'more'],
            ['page_id' => $this->files, 'placement' => 'nowhere'],
            ['page_id' => $this->files, 'placement' => 'both'],
        ]);

        $this->getJson('/api/v1/app-status', ['Accept-Language' => 'ar'])->assertOk()->assertJsonPath('data.pages', [
            ['slug' => 'contact', 'title' => 'صفحة contact', 'excerpt' => 'عن contact', 'placement' => 'services'],
            // a page listed twice keeps its first row; an unknown placement becomes More
            ['slug' => 'files', 'title' => 'صفحة files', 'excerpt' => 'عن files', 'placement' => 'more'],
        ]);
    }

    public function test_a_page_of_supported_blocks_comes_native_and_any_other_for_the_webview(): void
    {
        $this->listPages([['page_id' => $this->files, 'placement' => 'more'], ['page_id' => $this->form, 'placement' => 'more']]);

        $this->getJson('/api/v1/pages/files', ['Accept-Language' => 'ar'])->assertOk()
            ->assertJsonPath('data.title', 'صفحة files')
            ->assertJsonPath('data.native', true)
            ->assertJsonPath('data.blocks', [
                ['type' => 'cta', 'heading' => 'استمارة الملكية', 'text' => null, 'button_label' => 'Download', 'button_url' => url('/storage/9/form.pdf')],
                ['type' => 'divider'],
            ])
            ->assertJsonPath('data.web_url', route('page.show', 'files').'?app=1&lang=ar')
            ->assertJsonPath('data.share_url', route('page.show', 'files'));

        $this->getJson('/api/v1/pages/contact')->assertOk()
            ->assertJsonPath('data.native', false)
            ->assertJsonPath('data.blocks', [['type' => 'unsupported']]);

        // the page's own scripts only run on the website
        \DB::table('cms_pages')->where('id', $this->files)->update(['custom_body_scripts' => '<script>track()</script>']);
        $this->getJson('/api/v1/pages/files')->assertJsonPath('data.native', false);
    }

    public function test_only_listed_published_pages_can_be_read(): void
    {
        $this->listPages([['page_id' => $this->draft, 'placement' => 'more']]);

        $this->getJson('/api/v1/pages/draft')->assertNotFound();
        $this->getJson('/api/v1/pages/unlisted')->assertNotFound();
        $this->getJson('/api/v1/pages/missing')->assertNotFound();
    }

    public function test_the_website_knows_the_apps_webview(): void
    {
        $this->assertTrue(MobilePages::inApp(request()->duplicate(['app' => '1'])));

        $webView = request()->duplicate();
        $webView->headers->set('User-Agent', 'Mozilla/5.0 (iPhone) Mobile/15E148 MuhraApp/1.2.0');
        $this->assertTrue(MobilePages::inApp($webView));

        $browser = request()->duplicate(['app' => '0']);
        $browser->headers->set('User-Agent', 'Mozilla/5.0 (iPhone) Safari/604.1');
        $this->assertFalse(MobilePages::inApp($browser));
    }
}
