<?php

namespace App\Jobs;

use App\Models\MobileDevice;
use App\Models\PushSavedSearch;
use App\Services\Push\ExpoPush;
use App\Support\MobileApp\MobileAutoPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Throwable;

/** A post just went live: push it to the phones whose saved search on its board it matches (once each). Never throws. */
class NotifySavedSearches implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly string $section,
        public readonly int $postId,
    ) {}

    /** Called when a horse or tool post is created or updated: dispatched when it has just become visible. */
    public static function whenLive(Model $post, string $section, bool $created): void
    {
        if ($post->getAttribute('status') === 'active' && ($created || $post->wasChanged('status'))) {
            self::dispatch($section, $post->getKey())->afterCommit();
        }
    }

    public function handle(ExpoPush $push): void
    {
        try {
            [$model] = PushSavedSearch::SECTIONS[$this->section];
            $post = $model::query()->visible()->find($this->postId);

            if (! $post) {
                return;
            }

            $devices = MobileDevice::query()->whereKey(PushSavedSearch::deviceIdsFor($this->section, $post->getKey()))->get();

            $push->send($devices, fn () => [
                'title' => __('api.push.new_'.$this->section),
                'body' => MobileAutoPush::body($this->section, $post),
            ], ['path' => "/{$this->section}/{$post->getKey()}"]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
