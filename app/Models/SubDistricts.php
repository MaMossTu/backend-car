<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class SubDistricts extends Model
{
    use HasFactory,SoftDeletes;
    protected $softDelete = true;
    protected $hidden = ['deleted_at'];

    public function CustomerAddresses(){
        return $this->hasMany(CustomerAddresses::class);
    }

    public function Districts(){
        return $this->belongsTo(Districts::class, 'district_id');
    }
    public function Branches(){
        return $this->hasMany(Branches::class, 'branche_id');
    }
}
