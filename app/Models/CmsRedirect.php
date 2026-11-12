<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CmsRedirect extends Model
{
    protected $fillable = ['from_path', 'to_path', 'status_code', 'hits'];

    /**
     * How the 404 fallback (routes/web.php) looks a path up: no domain, query or slashes at either
     * end. "https://muhraequine.om/Old-Page/?x=1" → "Old-Page".
     */
    public static function normalizePath(?string $path): string
    {
        $path = (string) (parse_url(trim((string) $path), PHP_URL_PATH) ?? '');

        return trim(rawurldecode($path), '/');
    }

    public function setFromPathAttribute(?string $value): void
    {
        $this->attributes['from_path'] = static::normalizePath($value);
    }

    protected $casts = [
        'status_code' => 'integer',
        'hits' => 'integer',
    ];
}
