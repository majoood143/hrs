<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/** A file delivered to the customer. It lives on the private disk and is only served through an authorised route. */
class OrderDocument extends Model
{
    use LogsActivity;

    public const DISK = 'local';

    public const DIRECTORY = 'order-documents';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class, 'service_order_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'original_name', 'mime'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('OrderDocument');
    }

    public function exists(): bool
    {
        return Storage::disk(self::DISK)->exists($this->path);
    }

    public function humanSize(): string
    {
        $bytes = (int) $this->size;

        return match (true) {
            $bytes >= 1048576 => number_format($bytes / 1048576, 1).' MB',
            $bytes >= 1024 => number_format($bytes / 1024, 0).' KB',
            default => $bytes.' B',
        };
    }

    /** The name the customer's browser saves it under: the title with the original extension. */
    public function downloadName(): string
    {
        $extension = pathinfo((string) ($this->original_name ?: $this->path), PATHINFO_EXTENSION);
        $base = preg_replace('/[\\\\\/:*?"<>|]+/', '-', $this->title) ?: 'document';

        return $extension ? $base.'.'.$extension : $base;
    }
}
