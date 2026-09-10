<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CmsRedirect extends Model
{
    protected $fillable = ['from_path', 'to_path', 'status_code', 'hits'];

    protected $casts = [
        'status_code' => 'integer',
        'hits' => 'integer',
    ];
}
