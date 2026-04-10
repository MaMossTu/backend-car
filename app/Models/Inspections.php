<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inspections extends Model
{
    use HasFactory, SoftDeletes;
    protected $softDelete = true;
    protected $hidden = ['deleted_at'];
    protected $appends = [
        'photo_vehicle_gas_url',
    ];

    public function Employees()
    {
        return $this->belongsTo(Employees::class, 'employee_id', 'id');
    }
    public function Branches()
    {
        return $this->belongsTo(Branches::class, 'branch_id');
    }

    public function Vehicles()
    {
        return $this->belongsTo(Vehicles::class, 'vehicle_id', 'id');
    }

    public function InspectionVehicles()
    {
        return $this->hasMany(InspectionVehicles::class, 'inspection_id', 'id');
    }

    public function getPhotoVehicleGasUrlAttribute()
    {
        return $this->photo_vehicle_gas ? asset($this->photo_vehicle_gas) : null;
    }

    public function Installments()
    {
        return $this->hasMany(Installment::class, 'inspection_id', 'id');
    }

    public function save_document()
    {
        return $this->hasMany(Save_document::class, 'inspection_id', 'id');
    }
    public function Customers()
    {
        return $this->belongsTo(Customers::class, 'customer_id');
    }

    public function serviceTransactions()
    {
        return $this->hasMany(ServiceTransactions::class, 'inspections_id','id');
    }

    // public function insurance_link_phones()
    // {
    //     return $this->belongsTo(Insurance_link_phone::class, 'insurance_link_phone_id', 'id');
    // }
    // public function AddressEms(){
    //     return $this->belongsTo(AddressEms::class,'address_ems_id','id');
    // }
    public function docTransactions()
    {
        return $this->hasMany(Doc_transaction::class);
    }
    public function insurancesRenewals()
    {
        return $this->hasMany(insurancesRenewals::class,'inspection_id', 'id');
    }
    public function taxInvoiceAllNos()
    {
        return $this->hasMany(TaxInvoiceAllNos::class, 'inspection_id', 'id');
    }
}
