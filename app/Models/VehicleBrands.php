<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class VehicleBrands extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $softDelete = true;
    protected $hidden = ['deleted_at'];

    public function VehicleModels(){
        return $this->hasMany(VehicleModels::class, 'vehicle_brand_id');
    }
    public function Vehicles(){
        return $this->hasMany(Vehicles::class,'brand_id');
    }
}
