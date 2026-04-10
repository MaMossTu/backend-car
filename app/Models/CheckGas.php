<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CheckGas extends Model
{
    use HasFactory, SoftDeletes;
    protected $softDelete = true;
    protected $hidden = ['deleted_at'];
    protected $appends = ['photo_vehicle_gas_url'];


    public function inspectionVehicle()
    {
        return $this->belongsTo(InspectionVehicles::class, 'inspection_vehicle_id');
    }

    public function Customers()
    {
        return $this->belongsTo(Customers::class, 'engineer_id');
    }
    public function Chano()
    {
        return $this->hasMany(ChaNo::class);
    }
    public function getPhotoVehicleGasUrlAttribute()
    {
        return $this->photo_vehicle_gas ? asset($this->photo_vehicle_gas) : null;
    }
}
