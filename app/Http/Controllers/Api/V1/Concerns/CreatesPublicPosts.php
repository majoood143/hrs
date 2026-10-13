<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Services\PublicPosts\PublicPostCreator;
use App\Support\Api\PostingLimit;
use App\Support\Api\PostingToken;
use App\Support\MobileApp\MobilePosting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Posting from the app: the same fields, rules and creation as the website's forms (a
 * PublicPostCreator), with a verified phone (`posting_token` from verification/verify) in place
 * of the captcha. The post's contact number is that phone; it is `pending` (hidden until an admin
 * approves it) while admin → Mobile app → Posting → "Review app posts" is on.
 */
trait CreatesPublicPosts
{
    /**
     * @param  list<string>  $fileFields  single uploads; `images` is read as a list
     */
    protected function createPublicPost(Request $request, PublicPostCreator $creator, array $fileFields): Model
    {
        $phone = PostingToken::phone($request->input('posting_token'));

        if ($phone === null) {
            throw new HttpResponseException(response()->json([
                'message' => __('api.posting.verification_required'),
                'code' => 'verification_required',
            ], 401));
        }

        if (PostingLimit::reached($phone)) {
            throw new HttpResponseException(response()->json([
                'message' => __('api.posting.limit', ['count' => PostingLimit::perDay()]),
                'code' => 'posting_limit',
            ], 429));
        }

        $data = $request->validate($creator->rules(), [], $creator->attributes());

        $files = collect($fileFields)->mapWithKeys(fn (string $field) => [
            $field => $field === 'images' ? $request->file('images', []) : $request->file($field),
        ])->all();

        return $creator->create($data, $files, PostingLimit::contactNumber($phone), MobilePosting::newPostStatus());
    }

    /** 201 with the new post, and whether it waits for an admin's approval (then it is not visible yet). */
    protected function postedResponse(JsonResource $resource, Model $post): JsonResponse
    {
        return $resource->additional(['meta' => ['pending_review' => $post->getAttribute('status') === 'pending']])
            ->response()
            ->setStatusCode(201);
    }
}
