<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vaccination extends Model
{
    //
    protected $fillable = ['vaccine_type', 'date_administered', 'horse_id', 'manufacturer', 'batch_number', 'veterinarian_name', 'veterinarian_license', 'dosage', 'administration_route', 'adverse_reaction', 'reaction_details', 'notes'];
    
}
