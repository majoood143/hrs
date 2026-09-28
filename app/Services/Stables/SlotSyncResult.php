<?php

namespace App\Services\Stables;

/** What one run of the slot generator did. "kept" are slots it would have removed but that have bookings. */
final class SlotSyncResult
{
    public function __construct(
        public int $created = 0,
        public int $updated = 0,
        public int $removed = 0,
        public int $kept = 0,
    ) {}

    public function add(self $other): self
    {
        return new self(
            $this->created + $other->created,
            $this->updated + $other->updated,
            $this->removed + $other->removed,
            $this->kept + $other->kept,
        );
    }
}
