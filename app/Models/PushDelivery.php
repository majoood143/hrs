<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One campaign sent to one phone: its line in the app's inbox, and whether it was opened. */
class PushDelivery extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['push_campaign_id', 'mobile_device_id', 'opened_at'];

    protected $casts = ['opened_at' => 'datetime'];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(PushCampaign::class, 'push_campaign_id');
    }
}
