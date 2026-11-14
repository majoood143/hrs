<?php

namespace Tests\Feature\Api\V1;

use App\Services\PublicPosts\FarrierCreator;
use App\Services\PublicPosts\HorseSalePostCreator;
use App\Services\PublicPosts\JockeyCreator;
use App\Services\PublicPosts\ToolSalePostCreator;
use App\Services\PublicPosts\TransferPostCreator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/** The app shows the API's `message` and field errors as they are: in Arabic, not a word may stay English. */
class ArabicErrorMessagesTest extends TestCase
{
    private const AR = ['Accept-Language' => 'ar'];

    private function assertNoLatin(string $message): void
    {
        $this->assertMatchesRegularExpression('/\p{Arabic}/u', $message);
        $this->assertDoesNotMatchRegularExpression('/[A-Za-z]/', $message);
    }

    public function test_laravels_own_errors_are_translated(): void
    {
        foreach (['no-such-route' => 404, 'account' => 401] as $path => $status) {
            $this->assertNoLatin($this->getJson("/api/v1/{$path}", self::AR)->assertStatus($status)->json('message'));
        }

        $this->getJson('/api/v1/account', ['Accept-Language' => 'en'])->assertUnauthorized()->assertJsonPath('message', __('api.errors.401', locale: 'en'));

        config(['app.debug' => false]);
        $request = Request::create('/api/v1/horses-for-sale/1', server: ['HTTP_ACCEPT_LANGUAGE' => 'ar']);

        foreach ([[new RuntimeException('secret'), 500], [new ThrottleRequestsException, 429], [new ModelNotFoundException('App\\Models\\Secret'), 404], [new AuthorizationException, 403]] as [$exception, $status]) {
            $response = app(ExceptionHandler::class)->render($request, $exception);

            $this->assertSame($status, $response->getStatusCode());
            $this->assertSame(__("api.errors.{$status}", locale: 'ar'), $response->getData(true)['message']);
            $this->assertNoLatin($response->getData(true)['message']);
        }
    }

    /** @return array<string, array{string, string}> */
    public static function forms(): array
    {
        return [
            'report' => ['POST', 'reports'],
            'customer code' => ['POST', 'auth/request'],
            'customer verify' => ['POST', 'auth/verify'],
            'device' => ['POST', 'devices'],
            'saved search' => ['POST', 'push/searches'],
            'racing search' => ['GET', 'racing/search'],
            'app events' => ['POST', 'events'],
        ];
    }

    #[DataProvider('forms')]
    public function test_field_errors_and_their_summary_are_arabic(string $method, string $path): void
    {
        $response = $this->json($method, "/api/v1/{$path}", [], self::AR)->assertUnprocessable();

        $this->assertNoLatin($response->json('message'));

        foreach (Arr::flatten($response->json('errors')) as $message) {
            $this->assertNoLatin($message);
        }
    }

    public function test_every_laravel_rule_has_an_arabic_message(): void
    {
        $english = Arr::dot(require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php'));
        $arabic = Arr::dot(require lang_path('ar/validation.php'));

        $this->assertSame([], array_keys(array_diff_key(Arr::except($english, ['custom.attribute-name.rule-name', 'attributes']), $arabic)));
    }

    public function test_every_posting_and_booking_field_has_an_arabic_name(): void
    {
        app()->setLocale('ar');
        $names = trans('validation.attributes');

        foreach ([HorseSalePostCreator::class, ToolSalePostCreator::class, FarrierCreator::class, JockeyCreator::class, TransferPostCreator::class] as $creator) {
            $creator = app($creator);

            foreach (array_keys($creator->rules()) as $field) {
                $this->assertNoLatin($creator->attributes()[$field] ?? $names[$field] ?? $field);
            }
        }

        foreach (['riders_count', 'name', 'email', 'payment_option', 'package_purchase_id', 'riders', 'waiver', 'gender', 'date_of_birth', 'national_id', 'marketing_opt_in', 'country', 'events', 'events.*.name'] as $field) {
            $this->assertNoLatin($names[$field] ?? $field);
        }

        $this->assertNoLatin(Validator::make(['images' => ['x']], ['images.*' => 'image'])->errors()->first());
    }
}
