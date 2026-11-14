<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One close or reopen of the books (Services\Reports\ClosedBooks); the latest row is in force. */
class BookClosing extends Model
{
    protected $fillable = ['closed_through', 'user_id', 'note'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
