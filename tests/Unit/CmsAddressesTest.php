<?php

namespace Tests\Unit;

use App\Models\CmsPage;
use App\Models\CmsRedirect;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** The addresses an admin types for CMS pages and redirects, read the way the routes read them. */
class CmsAddressesTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function redirectPaths(): array
    {
        return [
            'leading slash' => ['/old-page', 'old-page'],
            'trailing slash' => ['old-page/', 'old-page'],
            'full link with a query' => ['https://muhraequine.om/old/page/?x=1', 'old/page'],
            'encoded Arabic' => ['/%D8%A7%D9%84%D8%AE%D9%8A%D9%88%D9%84', 'الخيول'],
        ];
    }

    #[DataProvider('redirectPaths')]
    public function test_a_redirect_is_stored_as_the_fallback_looks_it_up(string $typed, string $stored): void
    {
        $this->assertSame($stored, CmsRedirect::normalizePath($typed));
    }

    public function test_a_page_cannot_take_an_address_a_section_of_the_site_serves(): void
    {
        foreach (['racing-news', 'events-2026', 'stables-guide', 'admin', 'blog'] as $slug) {
            $this->assertFalse(CmsPage::slugIsAllowed($slug), $slug);
        }

        // a section's own name gives it its SEO texts; other pages are fine
        foreach (['stables', 'racing', 'about-us', 'horses-database', 'jockey-registration-form'] as $slug) {
            $this->assertTrue(CmsPage::slugIsAllowed($slug), $slug);
        }
    }
}
