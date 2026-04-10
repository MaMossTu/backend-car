<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PrbRenewals extends Model
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
}
