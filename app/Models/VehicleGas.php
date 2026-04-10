<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VehicleGas extends Model
{
    use HasFactory, SoftDeletes;
    protected $softDelete = true;
    protected $hidden = ['deleted_at'];

    public function Vehicles(){
        return $this->belongsTo(Vehicles::class,  'vehicle_id', 'id');
    }
    public function ChaNo(){
        return $this->hasMany(ChaNo::class);
    }

}
