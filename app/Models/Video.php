<?php

namespace App\Models;

use App\Filament\RichEditor\TextDirectionPlugin;
use App\Support\YouTube;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class Video extends Model
{
    use HasTranslations;

    protected $fillable = ['folder_id', 'title', 'description', 'slug', 'youtube_url', 'order', 'is_active'];

    public array $translatable = ['title', 'description'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(VideoFolder::class, 'folder_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * The description as safe HTML (rich text from the admin editor, sanitized,
     * keeping alignment, `dir` and links), or null when empty.
     */
    public function descriptionHtml(): ?string
    {
        $html = $this->description;

        if (blank(strip_tags((string) $html))) {
            return null;
        }

        return RichContentRenderer::make($html)
            ->plugins([TextDirectionPlugin::make()])
            ->toHtml();
    }

    /** The description as plain text, for cards and SEO. */
    public function descriptionText(): ?string
    {
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(
            strip_tags(preg_replace('/<\/(p|h[1-6]|li|blockquote)>|<br\s*\/?>/i', '$0 ', (string) $this->description)),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8',
        )));

        return $text === '' ? null : $text;
    }

    public function getYoutubeIdAttribute(): ?string
    {
        return YouTube::videoId($this->youtube_url);
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        return $this->youtube_id ? "https://img.youtube.com/vi/{$this->youtube_id}/hqdefault.jpg" : null;
    }

    public function getEmbedUrlAttribute(): ?string
    {
        return $this->youtube_id ? "https://www.youtube-nocookie.com/embed/{$this->youtube_id}" : null;
    }
}
