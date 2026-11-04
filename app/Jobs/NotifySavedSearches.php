<?php

namespace App\Jobs;

use App\Models\PushSavedSearch;
use App\Models\SiteSetting;
use App\Services\Push\ExpoPush;
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

            // shortcut: one query per saved search; group searches by filters if there are thousands
            $devices = PushSavedSearch::query()->with('device')->where('section', $this->section)->get()
                ->filter(fn (PushSavedSearch $search) => $model::query()->visible()->whereKey($post->getKey())->filteredBy($search->filters)->exists())
                ->pluck('device')
                ->filter()
                ->unique('id')
                ->values();

            $push->send($devices, fn () => [
                'title' => __('api.push.new_'.$this->section),
                'body' => trim($post->name.' · '.SiteSetting::formatCurrency((float) $post->price), ' ·'),
            ], ['path' => "/{$this->section}/{$post->getKey()}"]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
