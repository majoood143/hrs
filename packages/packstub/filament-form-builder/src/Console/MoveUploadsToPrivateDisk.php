<?php

namespace Packstub\FormBuilder\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Files uploaded before uploads became private sit on a public disk, reachable by anyone who has
 * the link. This moves them to the private disk under the same path, so submissions keep pointing at them.
 */
class MoveUploadsToPrivateDisk extends Command
{
    protected $signature = 'form-builder:move-uploads-private {--dry-run : List what would move without moving it}';

    protected $description = 'Move form uploads from the legacy public disk(s) to the private uploads disk.';

    public function handle(): int
    {
        $target = Storage::disk((string) config('packstub-form-builder.uploads.disk', 'local'));
        $directory = trim((string) config('packstub-form-builder.uploads.directory', 'form-uploads'), '/');
        $moved = 0;

        foreach ((array) config('packstub-form-builder.uploads.legacy_disks', []) as $legacy) {
            $source = Storage::disk($legacy);

            foreach ($source->allFiles($directory) as $path) {
                if ($this->option('dry-run')) {
                    $this->line("would move [{$legacy}] {$path}");
                    $moved++;

                    continue;
                }

                // copy, check, then delete: a file is never removed before its copy is confirmed
                if ($target->put($path, $source->readStream($path)) && $target->exists($path) && $target->size($path) === $source->size($path)) {
                    $source->delete($path);
                    $moved++;
                } else {
                    $this->error("Could not move {$path}; left where it was.");
                }
            }
        }

        $this->info(($this->option('dry-run') ? 'Would move ' : 'Moved ').$moved.' file(s).');

        return self::SUCCESS;
    }
}
