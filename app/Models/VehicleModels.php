<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class VehicleModels extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $softDelete = true;
    protected $hidden = ['deleted_at'];

    public function VehicleBrands(){
        return $this->belongsTo(VehicleBrands::class, 'vehicle_brand_id');
    }
    public function VehicleYears(){
        return $this->hasMany(VehicleYears::class, 'vehicle_model_id');
    }
    public function Vehicles(){
        return $this->hasMany(Vehicles::class);
    }
   

}
