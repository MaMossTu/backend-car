<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicles extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $softDelete = true;
    protected $hidden = ['deleted_at'];
    protected $appends = [
        'file_url',
    ];

    public function VehicleInspectionTypes()
    {
        return $this->belongsTo(VehicleInspectionTypes::class, 'vehicle_inspection_types_id', 'id');
    }
    public function InsuranceTypes()
    {
        return $this->belongsTo(InsuranceTypes::class, 'insurance_types_id');
    }
    public function VehicleBrands()
    {
        return $this->belongsTo(VehicleBrands::class, 'brand_id');
    }
    public function VehicleModels()
    {
        return $this->belongsTo(VehicleModels::class, 'model_id');
    }
    public function Provinces()
    {
        return $this->belongsTo(Provinces::class, 'province_id');
    }
    public function customers()
    {
        return $this->belongsTo(Customers::class, 'customer_id');
    }
    public function WorkOrders()
    {
        return $this->hasMany(WorkOrder::class, 'vehicle_id');
    }
    public function getFileUrlAttribute()
    {
        return $this->file ? asset($this->file) : null;
    }
    public function VehicleGas()
    {
        return $this->hasMany(VehicleGas::class, 'vehicle_id', 'id');
    }
    public function Inspections()
    {
        return $this->hasMany(Inspections::class, 'vehicle_id', 'id');
    }
    public function insurancesrenewals()
    {
        return $this->hasMany(InsurancesRenewals::class, 'insurance_names_id', 'id');
    }
    public function prbrenewals()
    {
        return $this->hasMany(PrbRenewals::class, 'vehicle_id', 'id');
    }
    public function InspectionVehicle()
    {
        return $this->hasMany(InspectionVehicles::class, 'inspection_id', 'id');
    }
    public function Branches()
    {
        return $this->belongsTo(Branches::class, 'brand_id', 'id');
    }
}
