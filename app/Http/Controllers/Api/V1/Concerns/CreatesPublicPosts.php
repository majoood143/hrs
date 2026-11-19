<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Services\PublicPosts\PublicPostCreator;
use App\Support\Api\PostingLimit;
use App\Support\Api\PostingToken;
use App\Support\MobileApp\MobilePosting;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Cache;

/**
 * Posting from the app: the same fields, rules and creation as the website's forms (a
 * PublicPostCreator), with a verified phone (`posting_token` from verification/verify) in place
 * of the captcha (or a signed-in customer's bearer token: their phone). The post's contact number is that phone; it is `pending` (hidden until an admin
 * approves it) while admin → Mobile app → Posting → "Review app posts" is on.
 */
trait CreatesPublicPosts
{
    /**
     * @param  list<string>  $fileFields  single uploads; `images` is read as a list
     */
    protected function createPublicPost(Request $request, PublicPostCreator $creator, array $fileFields): Model
    {
        // a signed-in customer's phone was confirmed by the sign-in code: no second check
        $phone = PostingToken::phone($request->input('posting_token')) ?? $request->user('customer_api')?->phone;

        if ($phone === null) {
            throw new HttpResponseException(response()->json([
                'message' => __('api.posting.verification_required'),
                'code' => 'verification_required',
            ], 401));
        }

        $this->refuseOverLimit($phone);

        $data = $request->validate($creator->rules(), [], $creator->attributes());

        $files = collect($fileFields)->mapWithKeys(fn (string $field) => [
            $field => $field === 'images' ? $request->file('images', []) : $request->file($field),
        ])->all();

        // one post at a time per phone, so posts sent together cannot all pass the daily limit
        try {
            return Cache::lock('posting|'.$phone, 60)->block(20, function () use ($phone, $creator, $data, $files): Model {
                $this->refuseOverLimit($phone);

                return $creator->create($data, $files, PostingLimit::contactNumber($phone), MobilePosting::newPostStatus());
            });
        } catch (LockTimeoutException) {
            throw $this->overLimit();
        }
    }

    private function refuseOverLimit(string $phone): void
    {
        if (PostingLimit::reached($phone)) {
            throw $this->overLimit();
        }
    }

    private function overLimit(): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'message' => __('api.posting.limit', ['count' => PostingLimit::perDay()]),
            'code' => 'posting_limit',
        ], 429));
    }

    /** 201 with the new post, and whether it waits for an admin's approval (then it is not visible yet). */
    protected function postedResponse(JsonResource $resource, Model $post): JsonResponse
    {
        return $resource->additional(['meta' => ['pending_review' => $post->getAttribute('status') === 'pending']])
            ->response()
            ->setStatusCode(201);
    }
}
