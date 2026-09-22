<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Events\ServiceOrderCompleted;
use App\Events\ServiceOrderRejected;
use App\Jobs\SendStageReviewNotification;
use App\Models\OrderStage;
use App\Models\ServiceOrder;
use App\Support\FormOrderSettings;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * What admins do with an order once it is in.
 *
 * A form may define review stages (in its "Service & payment" tab). When such an order comes in it gets
 * a copy of them and is "in review": the stages run in order, and anyone holding a stage's role may
 * approve or reject it while it is the current one. A rejection ends the request (and, if it was paid,
 * a refund is due); the last approval moves it to "processing". An order is then completed by an admin
 * (which tells the customer), once every stage is approved and, if the form asks for it, a result
 * document has been uploaded. A form with no stages skips straight to completing.
 */
class OrderWorkflow
{
    public const OK = 'ok';

    /** The person may not decide this stage. */
    public const NOT_ALLOWED = 'not_allowed';

    /** There is no stage waiting for a decision (already decided, finished, rejected...). */
    public const NO_STAGE = 'no_stage';

    public const BLOCKER_STAGES = 'pending_stages';

    public const BLOCKER_DOCUMENT = 'needs_document';

    /** Statuses an order can be completed from. */
    private const COMPLETABLE = [OrderStatus::New, OrderStatus::InReview, OrderStatus::Processing];

    // ── Starting the review ──────────────────────────────────────────────────

    /**
     * Give an order that has just come in its review stages (a copy of the form's). Does nothing for a
     * form without stages, or if the order already has them.
     */
    public function startReview(ServiceOrder $order): bool
    {
        $order->loadMissing('form');
        $stages = $order->form ? FormOrderSettings::for($order->form)->stages() : [];

        if ($stages === [] || $order->stages()->exists()) {
            return false;
        }

        $started = DB::transaction(function () use ($order, $stages) {
            $locked = ServiceOrder::query()->whereKey($order->getKey())->lockForUpdate()->first();

            if (! $locked || $locked->status !== OrderStatus::New || $locked->stages()->exists()) {
                return false;
            }

            foreach ($stages as $index => $stage) {
                $locked->stages()->create(['position' => $index + 1, 'name' => $stage['name'], 'role_name' => $stage['role']]);
            }

            $locked->forceFill(['status' => OrderStatus::InReview])->save();
            $locked->recordEvent('review_started', __('orders.events.review_started'));

            return true;
        });

        if ($started) {
            $order->refresh();
            $this->announceStage($order, 1);
        }

        return $started;
    }

    // ── Deciding a stage ─────────────────────────────────────────────────────

    /** The stage waiting for a decision, if the order is in review. */
    public function currentStage(ServiceOrder $order): ?OrderStage
    {
        if ($order->status !== OrderStatus::InReview) {
            return null;
        }

        return $order->stages()->where('status', OrderStage::PENDING)->first();
    }

    /** Whether this person holds the role of the stage (or is a super admin). */
    public function mayDecide(?Authenticatable $user, OrderStage $stage): bool
    {
        if (! $user || ! method_exists($user, 'hasRole')) {
            return false;
        }

        return (bool) $user->hasRole([$stage->role_name, config('filament-shield.super_admin.name', 'super_admin')]);
    }

    public function canDecide(ServiceOrder $order, ?Authenticatable $user): bool
    {
        $stage = $this->currentStage($order);

        return $stage !== null && $this->mayDecide($user, $stage);
    }

    /** @return string one of OK, NOT_ALLOWED, NO_STAGE */
    public function approve(ServiceOrder $order, ?Authenticatable $user, ?string $comment = null): string
    {
        $next = null;

        $result = DB::transaction(function () use ($order, $user, $comment, &$next) {
            [$locked, $stage, $result] = $this->lockCurrent($order, $user);

            if ($result !== self::OK) {
                return $result;
            }

            $stage->forceFill(['status' => OrderStage::APPROVED, 'decided_by' => $user?->getAuthIdentifier(), 'decided_at' => now(), 'comment' => filled($comment) ? $comment : null])->save();
            $locked->recordEvent('stage_approved', $stage->name, ['stage' => $stage->position, 'comment' => $comment], public: false, userId: $user?->getAuthIdentifier());

            $following = $locked->stages()->where('status', OrderStage::PENDING)->first();

            if ($following) {
                $next = $following->position;
            } else {
                $locked->forceFill(['status' => OrderStatus::Processing])->save();
                $locked->recordEvent('approved', __('orders.events.approved'));
            }

            return self::OK;
        });

        if ($result === self::OK) {
            $order->refresh();
            $next !== null ? $this->announceStage($order, $next) : null;
        }

        return $result;
    }

    /** @return string one of OK, NOT_ALLOWED, NO_STAGE */
    public function reject(ServiceOrder $order, ?Authenticatable $user, string $reason): string
    {
        $result = DB::transaction(function () use ($order, $user, $reason) {
            [$locked, $stage, $result] = $this->lockCurrent($order, $user);

            if ($result !== self::OK) {
                return $result;
            }

            $stage->forceFill(['status' => OrderStage::REJECTED, 'decided_by' => $user?->getAuthIdentifier(), 'decided_at' => now(), 'comment' => $reason])->save();
            $locked->forceFill(['status' => OrderStatus::Rejected])->save();
            $locked->recordEvent('rejected', __('orders.events.rejected'), ['stage' => $stage->position, 'reason' => $reason], userId: $user?->getAuthIdentifier());

            return self::OK;
        });

        if ($result === self::OK) {
            $order->refresh();
            ServiceOrderRejected::dispatch($order);
        }

        return $result;
    }

    /**
     * Lock the order and its current stage and check the person may decide it.
     *
     * @return array{0: ?ServiceOrder, 1: ?OrderStage, 2: string}
     */
    private function lockCurrent(ServiceOrder $order, ?Authenticatable $user): array
    {
        $locked = ServiceOrder::query()->whereKey($order->getKey())->lockForUpdate()->first();
        $stage = $locked ? $this->currentStage($locked) : null;

        if (! $stage) {
            return [$locked, null, self::NO_STAGE];
        }

        return $this->mayDecide($user, $stage) ? [$locked, $stage, self::OK] : [$locked, $stage, self::NOT_ALLOWED];
    }

    /** Tell the people who hold a stage's role that an order is waiting for them. */
    private function announceStage(ServiceOrder $order, int $position): void
    {
        try {
            SendStageReviewNotification::dispatch($order->getKey(), $position);
        } catch (Throwable $e) {
            Log::error('Could not queue the review notification', ['order' => $order->order_number, 'error' => $e->getMessage()]);
        }
    }

    // ── Completing ───────────────────────────────────────────────────────────

    /**
     * What still stands in the way of completing the order (empty when nothing does).
     *
     * @return list<string>
     */
    public function completionBlockers(ServiceOrder $order): array
    {
        $blockers = [];

        if ($order->stages()->where('status', OrderStage::PENDING)->exists()) {
            $blockers[] = self::BLOCKER_STAGES;
        }

        if ($order->requires_document && ! $order->documents()->exists()) {
            $blockers[] = self::BLOCKER_DOCUMENT;
        }

        return $blockers;
    }

    public function canComplete(ServiceOrder $order): bool
    {
        return ($order->isPaid() || $order->isFree())
            && in_array($order->status, self::COMPLETABLE, true)
            && $this->completionBlockers($order) === [];
    }

    /**
     * Mark the order completed (once) and tell the customer.
     *
     * @return bool false when it was not in a state to be completed (already done, cancelled, rejected, unpaid,
     *              stages still pending, a required document missing...)
     */
    public function complete(ServiceOrder $order, ?int $userId = null): bool
    {
        $done = DB::transaction(function () use ($order, $userId) {
            $locked = ServiceOrder::query()->whereKey($order->getKey())->lockForUpdate()->first();

            if (! $locked || ! $this->canComplete($locked)) {
                return false;
            }

            $locked->forceFill(['status' => OrderStatus::Completed, 'completed_at' => now()])->save();
            $locked->recordEvent('completed', __('orders.events.completed'), userId: $userId);

            return true;
        });

        if ($done) {
            $order->refresh();
            ServiceOrderCompleted::dispatch($order);
        }

        return $done;
    }
}
