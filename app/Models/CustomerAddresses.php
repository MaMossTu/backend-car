<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerAddresses extends Model
{
    use HasFactory,SoftDeletes;
    protected $softDelete = true;
    protected $hidden = ['deleted_at'];

    protected $fillable = [
        'address',
        'customer_id',
        'subdistrict_id',
        'district_id',
        'province_id',
        'zip_code',
    ];

    public function Customer(){
        return $this->belongsTo(Customers::class, 'customer_id');
    }
    public function Provinces(){
        return $this->belongsTo(Provinces::class, 'province_id');
    }

    public function Districts(){
        return $this->belongsTo(Districts::class, 'district_id');
    }

    public function SubDistricts(){
        return $this->belongsTo(SubDistricts::class, 'subdistrict_id');
    }



}
