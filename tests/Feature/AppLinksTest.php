<?php

namespace Tests\Feature;

use App\Support\MobileApp\AppLinks;
use Tests\Concerns\PreparesMobileApi;
use Tests\TestCase;

/** The /.well-known files that let website links open the Muhra app. */
class AppLinksTest extends TestCase
{
    use PreparesMobileApi;

    private const FINGERPRINT = '14:6D:E9:83:C5:73:06:50:D8:EE:B9:95:2F:34:FC:64:16:A0:83:42:E6:1D:BE:A8:8A:04:96:B2:3F:CF:44:E5';

    public function test_without_keys_both_files_are_missing_so_links_keep_opening_the_website(): void
    {
        $this->seedApiSettings([]);

        $this->get('/.well-known/apple-app-site-association')->assertNotFound();
        $this->get('/.well-known/assetlinks.json')->assertNotFound();
    }

    public function test_the_apple_file_claims_the_sections_the_app_shows_for_the_team_app_id(): void
    {
        $this->seedApiSettings(['mobile.links' => json_encode(['apple_team_id' => 'abcde12345'])]);

        $response = $this->get('/.well-known/apple-app-site-association')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('applinks.details.0.appIDs', ['ABCDE12345.om.muhra.app']);

        $paths = collect($response->json('applinks.details.0.components'))->reject(fn ($c) => $c['exclude'] ?? false)->pluck('/');
        $this->assertContains('/', $paths);
        $this->assertContains('/horses-for-sale/*', $paths);
        $this->assertContains('/video-library', $paths);
        $this->assertNotContains('/racing/*', $paths);
        $this->assertSame('/*/captcha', $response->json('applinks.details.0.components.0./'));
        $this->assertStringNotContainsString('\/', $response->getContent());
    }

    public function test_the_android_file_lists_the_signing_fingerprints(): void
    {
        $this->seedApiSettings(['mobile.links' => json_encode(['android_fingerprints' => self::FINGERPRINT])]);

        $this->get('/.well-known/assetlinks.json')
            ->assertOk()
            ->assertJsonPath('0.relation', ['delegate_permission/common.handle_all_urls'])
            ->assertJsonPath('0.target.package_name', 'om.muhra.app')
            ->assertJsonPath('0.target.sha256_cert_fingerprints', [self::FINGERPRINT]);
    }

    public function test_fingerprints_are_read_with_or_without_colons_and_bad_ones_dropped(): void
    {
        $bare = strtolower(str_replace(':', '', self::FINGERPRINT));

        $this->assertSame([self::FINGERPRINT], AppLinks::fingerprints("{$bare}\n".self::FINGERPRINT.', AB:CD'));
        $this->assertSame([], AppLinks::fingerprints(''));
    }
}
