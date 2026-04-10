<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class VehicleInspectionTypes extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $softDelete = true;
    protected $hidden = ['deleted_at'];

    public function Vehicles(){
        return $this->hasMany(Vehicles::class, 'vehicle_id', 'id');
    }
    public function InsuranceTypes(){
        return $this->hasMany(InsuranceTypes::class,'vehicle_inspection_types_id','id');
    }
    public function setting(){
        return $this->hasMany(Setting::class,'vehicle_inspection_types_id','id');
    }
}
