<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vaccination extends Model
{
    protected $fillable = ['vaccine_type', 'date_administered', 'horse_id', 'manufacturer', 'batch_number', 'veterinarian_name', 'veterinarian_license', 'dosage', 'administration_route', 'adverse_reaction', 'reaction_details', 'notes'];

    protected $casts = [
        'date_administered' => 'date',
    ];

    public function horse(): BelongsTo
    {
        return $this->belongsTo(Horse::class);
    }
}
