<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Events\ServiceOrderReceived;
use App\Models\PaymentGatewayLog;
use App\Models\PaymentGatewaySession;
use App\Models\ServiceOrder;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\PreparesOrderSite;
use Tests\TestCase;

class OmpayPaymentTest extends TestCase
{
    use PreparesOrderSite;

    private const API = 'https://api.sandbox.truepay.ompay.om/api/v1/transactions';

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([ServiceOrderReceived::class]);
        $this->prepareOrderSite([
            'enabled_gateways' => ['ompay'],
            'ompay.api_key' => 'key_test',
            'ompay.api_secret' => 'secret_test',
            'ompay.test_mode' => true,
        ]);
    }

    /** @param  array<string, array<string, mixed>>  $answers  what the inquiry says per transaction id */
    private function fakeInquiry(array $answers): void
    {
        Http::swap(new Factory);
        Http::fake([self::API.'/inquiry' => function (Request $request) use ($answers) {
            return Http::response(['success' => true, 'data' => $answers[$request['transaction_id']] + ['transaction_id' => $request['transaction_id'], 'amount' => 11.025, 'currency' => 'OMR']]);
        }]);
    }

    private function orderWithTransactions(string ...$ids): ServiceOrder
    {
        $order = $this->makeOrder();

        foreach ($ids as $id) {
            PaymentGatewaySession::record($order, 'ompay', $id);
        }

        $order->update(['payment_session_id' => end($ids), 'payment_method' => PaymentGateway::Ompay]);

        return $order->refresh();
    }

    private function returnFrom(ServiceOrder $order, array $query = [])
    {
        return $this->get(route('payment.ompay.return', ['reference' => $order->order_number] + $query));
    }

    public function test_paying_creates_a_transaction_for_the_whole_total_and_redirects_to_ompay(): void
    {
        Http::fake([self::API.'/bank-hosted' => Http::response(['success' => true, 'data' => [
            'transaction_id' => 'tx_1', 'status' => 'PENDING', 'redirect_url' => self::API.'/tx_1/pay-page',
        ]])]);
        $order = $this->makeOrder();

        $this->post(route('payment.begin', $order->order_number), ['gateway' => 'ompay'])
            ->assertRedirect(self::API.'/tx_1/pay-page');

        Http::assertSent(fn (Request $request) => $request->header('OMPAY-API-Key') === ['key_test']
            && $request->header('OMPAY-API-Secret') === ['secret_test']
            && $request['amount'] === 11.025
            && $request['currency'] === 'OMR'
            && str_starts_with($request['reference_number'], $order->order_number.'-')
            && str_contains($request['return_url'], 'payment/ompay/return?reference='.$order->order_number));

        $order->refresh();
        $this->assertSame('tx_1', $order->payment_session_id);
        $this->assertSame(PaymentGateway::Ompay, $order->payment_method);
        $this->assertSame($order->id, PaymentGatewaySession::findOrder('ompay', 'tx_1')->id);
        $this->assertSame('pending', PaymentGatewayLog::where('event', 'create_session')->first()->outcome);
    }

    public function test_a_retry_checks_the_earlier_attempt_and_uses_a_new_reference(): void
    {
        $references = [];
        Http::fake([
            self::API.'/inquiry' => Http::response(['success' => true, 'data' => ['transaction_id' => 'tx_1', 'status' => 'FAILED', 'amount' => 11.025]]),
            self::API.'/bank-hosted' => function (Request $request) use (&$references) {
                $references[] = $request['reference_number'];

                return Http::response(['success' => true, 'data' => ['transaction_id' => 'tx_2', 'redirect_url' => self::API.'/tx_2/pay-page']]);
            },
        ]);
        $order = $this->orderWithTransactions('tx_1');

        $this->post(route('payment.begin', $order->order_number), ['gateway' => 'ompay'])->assertRedirect(self::API.'/tx_2/pay-page');
        $this->post(route('payment.begin', $order->order_number), ['gateway' => 'ompay'])->assertRedirect(self::API.'/tx_2/pay-page');

        $this->assertCount(2, array_unique($references), 'OMPAY refuses a reference number it has seen');
    }

    public function test_a_refused_payment_keeps_the_customer_on_the_page(): void
    {
        Http::fake([self::API.'/bank-hosted' => Http::response(['success' => false, 'error' => 'MERCHANT_CAPABILITY_DISABLED'], 402)]);
        $order = $this->makeOrder();

        $this->from(route('payment.start', $order->order_number))
            ->post(route('payment.begin', $order->order_number), ['gateway' => 'ompay'])
            ->assertSessionHas('error', __('payments.could_not_start'));

        $this->assertTrue($order->refresh()->isPayable());
        $this->assertSame('error', PaymentGatewayLog::where('event', 'create_session')->first()->outcome);
    }

    public function test_a_successful_transaction_pays_the_order_once(): void
    {
        $this->fakeInquiry(['tx_1' => ['status' => 'SUCCESSFUL']]);
        $order = $this->orderWithTransactions('tx_1');

        $this->returnFrom($order)->assertRedirect(route('orders.show', $order->order_number));
        $this->returnFrom($order)->assertRedirect(route('orders.show', $order->order_number));

        $order->refresh();
        $this->assertTrue($order->isPaid());
        $this->assertSame('tx_1', $order->payment_reference);
        $this->assertSame(['created', 'paid'], $order->events->pluck('type')->all());
    }

    public function test_the_ids_in_the_return_link_are_not_believed(): void
    {
        $this->fakeInquiry(['tx_mine' => ['status' => 'FAILED'], 'tx_someone_else' => ['status' => 'SUCCESSFUL']]);
        $order = $this->orderWithTransactions('tx_mine');

        $this->returnFrom($order, ['transaction_id' => 'tx_someone_else', 'status' => 'SUCCESSFUL'])
            ->assertRedirect(route('payment.start', $order->order_number));

        $this->assertFalse($order->refresh()->isPaid());
        Http::assertNotSent(fn (Request $request) => $request['transaction_id'] === 'tx_someone_else');
    }

    public function test_paying_an_earlier_attempt_still_counts(): void
    {
        $this->fakeInquiry(['tx_1' => ['status' => 'SUCCESSFUL'], 'tx_2' => ['status' => 'PENDING']]);
        $order = $this->orderWithTransactions('tx_1', 'tx_2');

        $this->returnFrom($order)->assertRedirect(route('orders.show', $order->order_number));

        $this->assertSame('tx_1', $order->refresh()->payment_reference);
    }

    public function test_a_failed_or_cancelled_transaction_lets_the_customer_try_again(): void
    {
        $this->fakeInquiry(['tx_1' => ['status' => 'FAILED', 'error_message' => 'Payment cancelled by the customer.']]);
        $order = $this->orderWithTransactions('tx_1');

        $this->returnFrom($order)
            ->assertRedirect(route('payment.start', $order->order_number))
            ->assertSessionHas('error', __('payments.not_completed'));

        $order->refresh();
        $this->assertSame(PaymentStatus::Failed, $order->payment_status);
        $this->assertTrue($order->isPayable());
    }

    public function test_a_payment_the_bank_has_not_answered_warns_before_paying_again(): void
    {
        foreach ([['status' => 'PROCESSING'], ['status' => 'FAILED', 'outcome_unconfirmed' => true]] as $i => $answer) {
            $this->fakeInquiry(["tx_{$i}" => $answer]);
            $order = $this->orderWithTransactions("tx_{$i}");

            $this->returnFrom($order)->assertSessionHas('error', __('payments.bank_unconfirmed'));

            $this->assertSame(PaymentStatus::Pending, $order->refresh()->payment_status);
        }
    }

    public function test_a_different_amount_never_pays_the_order(): void
    {
        $this->fakeInquiry(['tx_1' => ['status' => 'SUCCESSFUL', 'amount' => 0.5]]);
        $order = $this->orderWithTransactions('tx_1');

        $this->returnFrom($order)->assertSessionHas('error', __('payments.paid_but_unconfirmed'));

        $this->assertFalse($order->refresh()->isPaid());
        $this->assertSame('error', PaymentGatewayLog::where('event', 'amount_mismatch')->first()->outcome);
    }

    public function test_the_status_check_settles_it_and_asks_ompay_at_most_every_few_seconds(): void
    {
        $this->fakeInquiry(['tx_1' => ['status' => 'PENDING']]);
        $order = $this->orderWithTransactions('tx_1');

        $this->getJson(route('payment.status', $order->order_number))->assertJson(['settled' => false]);
        $this->getJson(route('payment.status', $order->order_number))->assertJson(['settled' => false]);
        Http::assertSentCount(1);

        Cache::forget('payment-check.'.$order->id);
        $this->fakeInquiry(['tx_1' => ['status' => 'SUCCESSFUL']]);

        $this->getJson(route('payment.status', $order->order_number))->assertJson(['settled' => true]);
    }

    public function test_the_expiry_sweep_settles_a_paid_order_and_never_cancels_one_it_cannot_verify(): void
    {
        $this->fakeInquiry(['tx_1' => ['status' => 'SUCCESSFUL']]);
        $paid = $this->orderWithTransactions('tx_1');
        ServiceOrder::whereKey($paid->id)->update(['created_at' => now()->subHours(2)]);

        $this->artisan('orders:expire-pending')->assertSuccessful();
        $this->assertTrue($paid->refresh()->isPaid());

        Http::swap(new Factory);
        Http::fake([self::API.'/inquiry' => Http::response(['success' => false, 'error' => 'INTERNAL_SERVER_ERROR'], 500)]);
        $unknown = $this->orderWithTransactions('tx_2');
        ServiceOrder::whereKey($unknown->id)->update(['created_at' => now()->subHours(2)]);

        $this->artisan('orders:expire-pending')->assertSuccessful();
        $this->assertSame(OrderStatus::PendingPayment, $unknown->refresh()->status);
    }

    public function test_a_late_confirmation_revives_an_order_the_sweep_cancelled(): void
    {
        $this->fakeInquiry(['tx_1' => ['status' => 'SUCCESSFUL']]);
        $order = $this->orderWithTransactions('tx_1');
        ServiceOrder::whereKey($order->id)->update(['status' => OrderStatus::Cancelled, 'cancellation_source' => 'system', 'cancelled_at' => now()]);

        $this->artisan('orders:recover-late-payments')->assertSuccessful();

        $this->assertTrue($order->refresh()->isPaid());
    }
}
