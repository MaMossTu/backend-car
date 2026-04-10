<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InsurancesRenewals extends Model
{
    use HasFactory, SoftDeletes;
    protected $softDelete = true;
    protected $hidden = ['deleted_at'];

    public function vehicle()
    {
        return $this->belongsTo(Vehicles::class);
    }

    public function insurancename()
    {
        return $this->belongsTo(Insurance_name::class, 'insurance_names_id');
    }

    public function Insurance_renewal_types()
    {
        return $this->belongsTo(Insurance_renewal_types::class, 'insurance_renewal_type_id','id');
    }
    public function inspection()
    {
        return $this->belongsTo(Inspections::class,'inspection_id','id');
    }
    public function VechicleServicesTransactions()
    {
        return $this->belongsTo(VechicleServicesTransactions::class, 'inspection_vehicle_id');
    }
}
