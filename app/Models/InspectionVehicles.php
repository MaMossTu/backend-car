<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InspectionVehicles extends Model
{
    use HasFactory, SoftDeletes;
    protected $softDelete = true;
    protected $hidden = ['deleted_at'];

    public function inspections()
    {
        return $this->belongsTo(Inspections::class, 'inspection_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicles::class, 'vehicle_id');
    }

    public function vehicleServiceTransaction()
    {
        return $this->hasMany(VechicleServicesTransactions::class, 'inspection_vehicle_id');
    }

    public function CheckGas()
    {
        return $this->hasMany(CheckGas::class, 'inspection_vehicle_id');
    }
}
