<?php

namespace App\Models;

use App\Models\Concerns\RefusesDeleteWhileReferenced;
use Illuminate\Database\Eloquent\Model;

class Type extends Model
{
    use RefusesDeleteWhileReferenced;

    /** What refers to a horse type: `RefusesDeleteWhileReferenced` refuses to delete one still in use (horses cascade). */
    public const DELETE_REFERENCES = [
        'horses' => 'type_id',
        'horse_sale_posts' => 'type_id',
    ];

    //
    protected $fillable = ['en_name', 'ar_name'];

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'ar' ? $this->ar_name : $this->en_name;
    }
}
