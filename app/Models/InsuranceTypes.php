<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class InsuranceTypes extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $softDelete = true;
    protected $hidden = ['deleted_at'];

    public function VehicleInspectionTypes(){
        return $this->hasMany(VehicleInspectionTypes::class);
    }
    public function Vehicles(){
        return $this->belongsTo(Vehicles::class);
    }
}
