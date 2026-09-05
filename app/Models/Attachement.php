<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class Attachement extends Model
{
    //

    protected $fillable = [
        'name',
        'description',
        'is_visible_to_client',
        'is_active',
        'user_id',
        'is_approved',
        'file_path',
        'type',
        'horse_id',
    ];

    use HasFactory;

    public function horse(): BelongsTo
    {
        return $this->belongsTo(Horse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
