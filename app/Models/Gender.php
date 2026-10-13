<?php

namespace App\Models;

use App\Models\Concerns\RefusesDeleteWhileReferenced;
use Illuminate\Database\Eloquent\Model;

class Gender extends Model
{
    use RefusesDeleteWhileReferenced;

    /** What refers to a gender: `RefusesDeleteWhileReferenced` refuses to delete one still in use (horses cascade). */
    public const DELETE_REFERENCES = [
        'horses' => 'gender_id',
        'horse_sale_posts' => 'gender_id',
    ];

    //
    protected $fillable = ['en_name', 'ar_name'];

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'ar' ? $this->ar_name : $this->en_name;
    }
}
