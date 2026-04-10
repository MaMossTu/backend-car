<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChaNo extends Model
{
    use HasFactory, SoftDeletes;
    protected $softDelete = true;
    protected $hidden = ['deleted_at'];

    public function Checkgas()
    {
        return $this->belongsTo(CheckGas::class);
    }
    public function VehicleGas()
    {
        return $this->belongsTo(VehicleGas::class);
    }
}
