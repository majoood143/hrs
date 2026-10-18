<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\LogReader;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Downloads a file from storage/logs. The link is a short-lived signed URL
 * made by the admin Maintenance page (which is permission-gated); a plain
 * response streams the file, where a Livewire download would load it all
 * into memory.
 */
class LogDownloadController extends Controller
{
    public function __invoke(string $file): BinaryFileResponse
    {
        abort_unless(array_key_exists($file, LogReader::files()), 404);

        return response()->download(storage_path('logs/'.$file));
    }
}
