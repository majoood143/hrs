<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    /** The values of the `transactions.status` enum column, labelled by `admin_transaction.statuses.*`. */
    public const STATUSES = ['pending', 'completed', 'failed', 'refunded'];

    protected $fillable = [
        'horse_id',
        'service_id',
        'user_id',
        'amount',
        'currency',
        'description',
        'status',
    ];

    /** @return array<string, string> the statuses in the current language */
    public static function statusOptions(): array
    {
        return collect(self::STATUSES)->mapWithKeys(fn (string $status) => [$status => __('admin_transaction.statuses.'.$status)])->all();
    }

    public function horse()
    {
        return $this->belongsTo(Horse::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function attachement()
    {
        return $this->hasMany(Attachement::class);
    }
}
