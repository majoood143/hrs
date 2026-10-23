<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A race's results as sent to the admins by WhatsApp (App\Services\Racing\RaceResultAlerts). */
class RaceResultAlert extends Model
{
    protected $fillable = [
        'race_id', 'race_date', 'race_name', 'results_seen_at', 'sent_at', 'mode', 'sent_by',
        'recipients', 'video_url', 'video_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'race_date' => 'date',
            'results_seen_at' => 'datetime',
            'sent_at' => 'datetime',
            'video_sent_at' => 'datetime',
            'recipients' => 'integer',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
