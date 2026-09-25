<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Counts views of a public listing (its `views_count` column): once per visitor session,
 * ignoring crawlers and link-preview bots. The counter is bumped with a plain query so the
 * row's `updated_at` stays the real "last edited" time.
 */
class ViewCounter
{
    private const BOT_PATTERN = '/bot|crawl|spider|slurp|facebookexternalhit|embedly|preview|headless|lighthouse|curl|wget|python|httpclient|java\//i';

    /**
     * Returns true when this call counted a new view.
     */
    public static function record(Model $model, ?Request $request = null): bool
    {
        $request ??= request();

        if (self::isBot($request->userAgent())) {
            return false;
        }

        $session = $request->hasSession() ? $request->session() : null;
        $key = 'viewed_listings.'.$model->getTable().'.'.$model->getKey();

        if ($session?->has($key)) {
            return false;
        }

        $model->newQueryWithoutScopes()->toBase()
            ->where($model->getKeyName(), $model->getKey())
            ->increment('views_count');

        $model->setAttribute('views_count', (int) $model->getAttribute('views_count') + 1);
        $model->syncOriginalAttribute('views_count');

        $session?->put($key, true);

        return true;
    }

    public static function isBot(?string $userAgent): bool
    {
        return blank($userAgent) || preg_match(self::BOT_PATTERN, $userAgent) === 1;
    }
}
