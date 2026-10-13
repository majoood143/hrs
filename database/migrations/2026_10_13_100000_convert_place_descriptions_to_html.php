<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Stable, shop, clinic and center descriptions became rich text. Old ones are plain text whose line breaks
 * the page showed with `whitespace-pre-line`; turn each into paragraphs (blank
 * line) and <br> (single newline) so they look the same in the editor and site.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['stables', 'shops', 'clinics', 'centers'] as $table) {
            DB::table($table)
                ->where(fn ($query) => $query->whereNotNull('en_description')->orWhereNotNull('ar_description'))
                ->orderBy('id')
                ->each(function ($row) use ($table) {
                    $changes = [];

                    foreach (['en_description', 'ar_description'] as $column) {
                        $text = $row->{$column};

                        if (! is_string($text) || trim($text) === '' || $text !== strip_tags($text)) {
                            continue;
                        }

                        $changes[$column] = collect(preg_split('/\R\s*\R/u', trim($text)))
                            ->map(fn (string $p) => '<p>'.nl2br(e(trim($p)), false).'</p>')
                            ->implode('');
                    }

                    if ($changes) {
                        DB::table($table)->where('id', $row->id)->update($changes);
                    }
                });
        }
    }

    public function down(): void
    {
        // Rich text cannot be turned back into the original plain text faithfully.
    }
};
