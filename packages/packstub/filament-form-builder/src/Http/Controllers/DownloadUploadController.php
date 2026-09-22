<?php

namespace Packstub\FormBuilder\Http\Controllers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Packstub\FormBuilder\FormBuilder;
use Packstub\FormBuilder\Support\UploadLinks;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves an uploaded file to someone allowed to see the form's submissions: a valid signature
 * says the link was issued by us, and the gate says the person may use it. Always sent as a
 * download, never rendered in the browser (an uploaded HTML or SVG file must not run on our origin).
 */
class DownloadUploadController
{
    public function __invoke(string $token): StreamedResponse
    {
        $path = UploadLinks::resolve($token) ?? abort(404);

        abort_unless(Gate::allows((string) config('packstub-form-builder.uploads.download_ability', 'viewAny'), FormBuilder::formModel()), 403);

        $disks = [
            (string) config('packstub-form-builder.uploads.disk', 'local'),
            ...(array) config('packstub-form-builder.uploads.legacy_disks', []),
        ];

        foreach ($disks as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return Storage::disk($disk)->download($path, basename($path));
            }
        }

        abort(404);
    }
}
