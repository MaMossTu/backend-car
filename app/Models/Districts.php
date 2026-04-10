<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Districts extends Model
{
    use HasFactory,SoftDeletes;
    protected $softDelete = true;
    protected $hidden = ['deleted_at'];

    public function CustomerAddresses(){
        return $this->hasMany(CustomerAddresses::class);
    }
    public function SubDistricts(){
        return $this->hasMany(SubDistricts::class,'district_id');

    }
    public function Provinces(){
        return $this->hasMany(Provinces::class);

    }
    public function Branches(){
        return $this->hasMany(Branches::class, 'branche_id');
    }

}

