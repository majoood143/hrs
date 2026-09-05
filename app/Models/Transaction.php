<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{

    //
    protected $fillable = [
        'horse_id',
        'service_id',
        'user_id',
        'amount',
        'currency',
        'description',
        'name'
    ];
    
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
