<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use PhpOffice\PhpSpreadsheet\Calculation\Web\Service;

class ServiceTransactions extends Model
{
    use HasFactory, SoftDeletes;
    protected $softDelete = true;
    protected $hidden = ['deleted_at'];
    protected $appends = ['service_name'];

    public function service()
    {
        return $this->belongsTo(Services::class, 'service_id');
    }

    public function Inspections()
    {
        return $this->belongsTo(Inspections::class, 'inspections_id', 'id');
    }
    public function CheckGas()
    {
        return $this->belongsTo(CheckGas::class, 'inspections_id', 'id');
    }

    public function getServiceNameAttribute()
    {
        return $this->service->name ?? null;
    }
}
