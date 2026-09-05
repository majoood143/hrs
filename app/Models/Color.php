<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Color extends Model
{

    use LogsActivity;
    /**
     * Configure the activity log options
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'hex_code'])
            ->useLogName('Color')
            ->setDescriptionForEvent(fn(string $eventName) => "Color has been {$eventName}");
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name', 'hex_code'];
}
