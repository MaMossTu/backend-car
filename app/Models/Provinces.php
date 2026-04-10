<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Provinces extends Model
{
    use HasFactory,SoftDeletes;
    protected $softDelete = true;
    protected $hidden = ['deleted_at'];

    public function CustomerAddresses(){
        return $this->hasMany(CustomerAddresses::class);
    }

    public function Districts(){
        return $this->hasMany(Districts::class,'province_id');

    }

    public function Branches(){
        return $this->hasMany(Branches::class, 'branche_id');
    }

     public function Vehicles(){
        return $this->hasMany(Vehicles::class, 'province_id');
    }

}
