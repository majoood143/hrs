<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attachement extends Model
{
    //

    /** The values of the `attachements.type` enum column, labelled by `admin_attachment.types.*`. */
    public const TYPES = ['id_card', 'cr_card', 'passport', 'front_side', 'left_side', 'right_side', 'back_side', 'DNA', 'other'];

    /** Papers (passports, ID cards, DNA reports): on the private disk, opened only through Filament's signed links. */
    public const DISK = 'local';

    /** @return array<string, string> the types in the current language */
    public static function typeOptions(): array
    {
        return collect(self::TYPES)->mapWithKeys(fn (string $type) => [$type => __('admin_attachment.types.'.$type)])->all();
    }

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
        'transaction_id',
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
