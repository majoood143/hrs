<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Services\PublicPosts\PublicPostCreator;
use App\Support\Api\PostingLimit;
use App\Support\Api\PostingToken;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

/**
 * Posting from the app: the same fields, rules and creation as the website's forms (a
 * PublicPostCreator), with a verified phone (`posting_token` from verification/verify) in place
 * of the captcha. The post's contact number is that phone.
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
                'message' => __('api.posting.limit', ['count' => PostingLimit::PER_DAY]),
                'code' => 'posting_limit',
            ], 429));
        }

        $data = $request->validate($creator->rules(), [], $creator->attributes());

        $files = collect($fileFields)->mapWithKeys(fn (string $field) => [
            $field => $field === 'images' ? $request->file('images', []) : $request->file($field),
        ])->all();

        return $creator->create($data, $files, PostingLimit::contactNumber($phone));
    }
}
