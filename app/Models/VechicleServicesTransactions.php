<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VechicleServicesTransactions extends Model
{
    use HasFactory, SoftDeletes;
    protected $softDelete = true;
    protected $hidden = ['deleted_at'];

    public function inspectionVehicle()
    {
        return $this->belongsTo(InspectionVehicles::class);
    }

    public function service()
    {
        return $this->belongsTo(Services::class, 'service_id');
    }

    public function InsurancesRenewals()
    {
        return $this->hasMany(InsurancesRenewals::class, 'vechicle_services_transaction_id');
    }
    protected $appends = ['service_name'];
    public function getServiceNameAttribute()
    {
        return $this->service->name ?? null;
    }
}
