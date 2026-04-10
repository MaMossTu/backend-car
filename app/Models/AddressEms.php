<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AddressEms extends Model
{
    use HasFactory, SoftDeletes;
    protected $softDelete = true;
    protected $hidden = ['deleted_at'];
    protected $appends = ['photo_url'];

    public function getPhotoUrlAttribute()
    {
        return $this->photo ? asset($this->photo) : null;
    }

    public function inspections()
    {
        return $this->hasMany(Inspections::class,'inspection_id','id');
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
