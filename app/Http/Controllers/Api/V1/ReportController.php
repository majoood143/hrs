<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AppEvent;
use App\Models\ContentReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Report a visitor post (horses/tools for sale, farriers, transfer board, jockeys) from the app, for
 * an admin to look at (admin → Mobile app → Reports). Anonymous, like browsing.
 */
class ReportController extends Controller
{
    /**
     * Report a post
     *
     * `section` is one of horses-for-sale, tools-for-sale, farriers, transfer-board, jockeys; only
     * posts visitors can see. Reporting the same post again from the same install (`X-Install-Id`;
     * the same address without one) adds nothing.
     *
     * @unauthenticated
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'section' => ['required', 'string', Rule::in(array_keys(ContentReport::SECTIONS))],
            'id' => ['required', 'integer'],
            'reason' => ['required', 'string', Rule::in(ContentReport::REASONS)],
            /** What is wrong, in the visitor's words. */
            'details' => ['nullable', 'string', 'max:1000'],
        ]);

        $model = ContentReport::SECTIONS[$data['section']][0];
        $model::visible()->whereKey($data['id'])->firstOrFail();

        // once per app install (one carrier address is many phones), per address only without one
        $install = AppEvent::installId($request);

        ContentReport::query()->firstOrCreate(
            ['section' => $data['section'], 'post_id' => $data['id'], 'status' => ContentReport::STATUS_OPEN]
                + ($install !== null ? ['install_id' => $install] : ['ip' => $request->ip(), 'install_id' => null]),
            ['reason' => $data['reason'], 'details' => $data['details'] ?? null, 'ip' => $request->ip()],
        );

        return response()->json(['message' => __('api.reports.sent')], 201);
    }
}
