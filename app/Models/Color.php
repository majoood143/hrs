<?php

namespace App\Models;

use App\Models\Concerns\RefusesDeleteWhileReferenced;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Color extends Model
{
    use RefusesDeleteWhileReferenced;

    /** What refers to a colour: `RefusesDeleteWhileReferenced` refuses to delete one still in use (horses would keep a dangling id). */
    public const DELETE_REFERENCES = [
        'horses' => 'color_id',
        'horse_sale_posts' => 'color_id',
    ];

    use LogsActivity;

    /**
     * Configure the activity log options
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['en_name', 'ar_name', 'hex_code'])
            ->useLogName('Color')
            ->setDescriptionForEvent(fn (string $eventName) => "Color has been {$eventName}");
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['en_name', 'ar_name', 'hex_code'];

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'ar' ? $this->ar_name : $this->en_name;
    }
}
