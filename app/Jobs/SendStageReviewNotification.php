<?php

namespace App\Jobs;

use App\Mail\StageReviewMail;
use App\Models\NotificationLog;
use App\Models\OrderStage;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Throwable;

/**
 * Emails the people who hold a stage's role: an order is waiting for their decision. Each person is
 * told once per stage, and a failure is logged, never thrown (the order carries on regardless).
 */
class SendStageReviewNotification implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public const TYPE = 'stage_review';

    public int $tries = 1;

    /** Loops over every holder of the stage's role: bounded so one stalled send cannot hang the worker for the whole batch. */
    public int $timeout = 60;

    public function __construct(public readonly int $orderId, public readonly int $position) {}

    public function handle(): void
    {
        $order = ServiceOrder::query()->with(['service', 'submission'])->find($this->orderId);
        $stage = $order?->stages()->where('position', $this->position)->first();

        if (! $order || ! $stage instanceof OrderStage || ! $stage->isPending()) {
            return;
        }

        try {
            $reviewers = $this->reviewers($stage);
        } catch (RoleDoesNotExist $e) {
            // The role snapshotted on this stage was renamed or deleted after review started.
            // A super_admin can still approve/reject it (OrderWorkflow::mayDecide always allows
            // that role), but nobody was told the stage exists: leave a visible trail instead of
            // failing silently (tries=1 means this job never retries).
            Log::critical('Stage review role no longer exists: nobody could be notified', [
                'order' => $order->order_number,
                'stage' => $stage->position,
                'role' => $stage->role_name,
            ]);
            $order->recordEvent('stage_role_missing', __('orders.events.stage_role_missing'), ['stage' => $stage->position, 'role' => $stage->role_name], public: false);

            return;
        }

        foreach ($reviewers as $user) {
            if (blank($user->email) || $this->alreadyTold($order, $user->email)) {
                continue;
            }

            try {
                Mail::to($user->email)->send(new StageReviewMail($order, $stage));
                $this->log($order, $user->email, 'sent');
            } catch (Throwable $e) {
                report($e);
                $this->log($order, $user->email, 'failed', $e->getMessage());
            }
        }
    }

    /** @return Collection<int, User> */
    private function reviewers(OrderStage $stage)
    {
        return User::role($stage->role_name)->get();
    }

    private function alreadyTold(ServiceOrder $order, string $address): bool
    {
        return NotificationLog::query()
            ->where('service_order_id', $order->getKey())
            ->where('type', self::TYPE)
            ->where('provider_reference', (string) $this->position)
            ->where('recipient', $address)
            ->where('status', 'sent')
            ->exists();
    }

    private function log(ServiceOrder $order, string $address, string $status, ?string $error = null): void
    {
        NotificationLog::create([
            'service_order_id' => $order->getKey(),
            'channel' => 'email',
            'type' => self::TYPE,
            'recipient' => $address,
            'status' => $status,
            'provider_reference' => (string) $this->position,
            'error' => $error,
        ]);
    }
}
