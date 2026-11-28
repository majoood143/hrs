<?php

namespace Tests\Feature\Games;

use App\Http\Resources\V1\PageResource;
use App\Models\CmsPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\MakesGames;
use Tests\TestCase;

/** The app's WebView signing in to the website as the app's customer, for a page with a game. */
class AppWebSessionTest extends TestCase
{
    use MakesGames;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareGames();
        (require database_path('migrations/2026_09_11_145142_create_personal_access_tokens_table.php'))->up();
    }

    public function test_the_link_signs_the_webview_in_once_and_opens_the_page(): void
    {
        $customer = $this->player();
        $token = $customer->createToken('app')->plainTextToken;

        $url = $this->withToken($token)->postJson('/api/v1/account/web-session', ['path' => '/race-challenge?app=1&lang=ar'])
            ->assertOk()->assertJsonPath('expires_in', 120)->json('url');
        $this->app['auth']->forgetGuards();

        $this->get($url)->assertRedirect('/race-challenge?app=1&lang=ar');
        $this->assertSame($customer->id, Auth::guard('customer')->id());

        // a second time it no longer signs anyone in
        Auth::guard('customer')->logout();
        $this->get($url)->assertRedirect('/');
        $this->assertNull(Auth::guard('customer')->id());
    }

    public function test_only_a_path_of_this_site_and_only_a_signed_in_customer(): void
    {
        $this->postJson('/api/v1/account/web-session', ['path' => '/x'])->assertUnauthorized();

        $token = $this->player()->createToken('app')->plainTextToken;
        foreach (['//evil.example/x', 'https://evil.example', '/\\evil.example', 'x'] as $path) {
            $this->withToken($token)->postJson('/api/v1/account/web-session', ['path' => $path])->assertStatus(422)->assertJsonValidationErrors('path');
        }
    }

    public function test_an_expired_link_signs_nobody_in(): void
    {
        $url = $this->withToken($this->player()->createToken('app')->plainTextToken)->postJson('/api/v1/account/web-session', ['path' => '/race-challenge'])->json('url');
        $this->app['auth']->forgetGuards();
        $this->travel(3)->minutes();

        $this->get($url)->assertRedirect('/');
        $this->assertNull(Auth::guard('customer')->id());
    }

    public function test_a_page_with_a_game_asks_the_app_to_sign_its_webview_in(): void
    {
        $page = new CmsPage(['slug' => 'race-challenge']);
        $page->content = [['type' => 'heading', 'data' => []], ['type' => 'game_campaign', 'data' => ['campaign_id' => 1]]];
        $plain = new CmsPage(['slug' => 'about']);
        $plain->content = [['type' => 'heading', 'data' => []]];

        $this->assertTrue((new PageResource($page))->toArray(Request::create('/'))['sign_in']);
        $this->assertFalse((new PageResource($plain))->toArray(Request::create('/'))['sign_in']);
        $this->assertFalse((new PageResource($page))->toArray(Request::create('/'))['native']);
    }
}
