<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Setting extends Model
{
    use HasFactory, SoftDeletes;
    protected $softDelete = true;
    protected $hidden = ['deleted_at'];

    public function service()
    {
        return $this->belongsTo(Services::class, 'service_id');
    }

    public function VehicleInspectionTypes()
    {
        return $this->belongsTo(VehicleInspectionTypes::class, 'vehicle_inspection_type_id' );
    }
}
