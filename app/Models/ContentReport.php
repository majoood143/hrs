<?php

namespace App\Models;

use App\Filament\Resources\FarrierResource;
use App\Filament\Resources\HorseSalePostResource;
use App\Filament\Resources\JockeyResource;
use App\Filament\Resources\ToolSalePostResource;
use App\Filament\Resources\TransferPostResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A visitor post reported from the app (`POST /api/v1/reports`), handled in admin → Mobile app →
 * Reports: the admin opens the post, hides or keeps it, and marks the report resolved.
 */
class ContentReport extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_RESOLVED = 'resolved';

    public const REASONS = ['spam', 'fraud', 'offensive', 'wrong_info', 'other'];

    /** Section (the API's path) => [model, admin resource]. */
    public const SECTIONS = [
        'horses-for-sale' => [HorseSalePost::class, HorseSalePostResource::class],
        'tools-for-sale' => [ToolSalePost::class, ToolSalePostResource::class],
        'farriers' => [Farrier::class, FarrierResource::class],
        'transfer-board' => [TransferPost::class, TransferPostResource::class],
        'jockeys' => [Jockey::class, JockeyResource::class],
    ];

    protected $fillable = ['section', 'post_id', 'reason', 'details', 'ip', 'status'];

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    /** The post's page in the admin, null when its section is unknown. */
    public function adminUrl(): ?string
    {
        $resource = self::SECTIONS[$this->section][1] ?? null;

        return $resource ? $resource::getUrl('view', ['record' => $this->post_id]) : null;
    }
}
