<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use Illuminate\Http\Request;

trait PaginatesListings
{
    protected const DEFAULT_PER_PAGE = 20;

    protected const MAX_PER_PAGE = 50;

    /**
     * `?per_page=`, capped at MAX_PER_PAGE (a bigger value is lowered, not refused).
     */
    protected function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', self::DEFAULT_PER_PAGE), 1), self::MAX_PER_PAGE);
    }
}
