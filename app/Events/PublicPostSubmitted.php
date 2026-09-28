<?php

namespace App\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A visitor published a post from the public site (transfer board, horse/tool for sale, farrier).
 * Fired by the site controllers and the /transportation panel, not by admin-created records.
 */
class PublicPostSubmitted
{
    use Dispatchable;

    public function __construct(public readonly Model $post) {}
}
