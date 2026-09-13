<?php

namespace App\Models;

use Ardavan\FilamentFileExplorer\Models\Concerns\HasFileExplorer;
use Illuminate\Database\Eloquent\Model;

class MediaLibrary extends Model
{
    use HasFileExplorer;

    protected $fillable = ['en_name', 'ar_name'];

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'ar' ? $this->ar_name : $this->en_name;
    }
}
