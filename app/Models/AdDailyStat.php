<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One promotion's impressions and clicks on one Oman day from one source (`web` | `app`). */
class AdDailyStat extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['date' => 'date'];
}
