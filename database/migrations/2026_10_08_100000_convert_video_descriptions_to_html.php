<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Video descriptions became rich text. Old ones are plain text whose line breaks
 * the page showed with `whitespace-pre-line`; turn each into paragraphs (blank
 * line) and <br> (single newline) so they look the same in the editor and site.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('videos')->whereNotNull('description')->orderBy('id')->each(function ($row) {
            $values = json_decode($row->description, true);

            if (! is_array($values)) {
                return;
            }

            $changed = false;

            foreach ($values as $locale => $text) {
                if (! is_string($text) || trim($text) === '' || $text !== strip_tags($text)) {
                    continue;
                }

                $paragraphs = preg_split('/\R\s*\R/u', trim($text));
                $values[$locale] = collect($paragraphs)
                    ->map(fn (string $p) => '<p>'.nl2br(e(trim($p)), false).'</p>')
                    ->implode('');
                $changed = true;
            }

            if ($changed) {
                DB::table('videos')->where('id', $row->id)->update([
                    'description' => json_encode($values, JSON_UNESCAPED_UNICODE),
                ]);
            }
        });
    }

    public function down(): void
    {
        // Rich text cannot be turned back into the original plain text faithfully.
    }
};
