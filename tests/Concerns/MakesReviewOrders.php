<?php

namespace Tests\Concerns;

use App\Enums\PaymentGateway;
use App\Models\ServiceOrder;
use App\Services\Orders\CreateServiceOrder;
use App\Services\Payments\OrderPaymentService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Support\Facades\Mail;

/**
 * Orders that go through review stages, and reviewers who hold roles, without the (MySQL-only) permission tables.
 */
trait MakesReviewOrders
{
    use PreparesCustomerSite;

    protected function prepareReviewSite(): void
    {
        $this->prepareCustomerSite();
        $this->useTamimah();
        $this->tamimahAnswers();
        Mail::fake();
    }

    /** A person holding the given roles (Spatie's hasRole() semantics: any of the roles asked about). */
    protected function reviewer(array $roles = [], int $id = 1): Authenticatable
    {
        return new class(['name' => 'Reviewer '.$id], $roles, $id) extends AuthUser
        {
            protected $guarded = [];

            public function __construct(array $attributes = [], private array $held = [], private int $userId = 1)
            {
                parent::__construct($attributes);
            }

            public function hasRole($roles): bool
            {
                return count(array_intersect((array) $roles, $this->held)) > 0;
            }

            public function getAuthIdentifier()
            {
                return $this->userId;
            }
        };
    }

    /** @return list<array{name: array<string, string>, role: string}> */
    protected function twoStages(): array
    {
        return [
            ['name' => ['en' => 'Technical check', 'ar' => 'فحص فني'], 'role' => 'reviewer'],
            ['name' => ['en' => 'Final approval', 'ar' => 'الموافقة النهائية'], 'role' => 'manager'],
        ];
    }

    /**
     * An order of a form with review stages, already received: paid through the demo gateway path
     * (or free), so the listeners have run exactly as in real life.
     */
    protected function stagedOrder(?array $stages = null, float $price = 10.0, array $approval = [], bool $paid = true): ServiceOrder
    {
        $this->makeFee('percentage', 5);
        $service = $this->makeService($price);
        $form = $this->makeForm($service, ['approval' => ['stages' => $stages ?? $this->twoStages()] + $approval], slug: 'form-'.uniqid());

        $order = app(CreateServiceOrder::class)->handle($service, [
            'name' => 'Ali Al Balushi', 'email' => 'ali@example.com', 'phone' => '96891234567',
        ], $form->id);

        if ($paid && $price > 0) {
            app(OrderPaymentService::class)->applyPaid($order, PaymentGateway::Demo, 'DEMO-REF');
        }

        return $order->refresh();
    }
}
