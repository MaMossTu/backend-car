<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Insurance_name extends Model
{

    use HasFactory, SoftDeletes;
    protected $softDelete = true;
    protected $hidden = ['deleted_at'];
    protected $appends = ['logo_url'];

    // public function insurance_link_phone()
    // {
    //     return $this->hasMany(Insurance_link_phone::class,'branch_id','id');
    // }

    public function getLogoUrlAttribute()
    {
    return $this->logo ? asset($this->logo) : null;
    }
    public function insurancesrenewals()
    {
        return $this->hasMany(InsurancesRenewals::class, 'insurance_names_id', 'id');
    }
    public function prbrenewals()
    {
        return $this->hasMany(PrbRenewals::class, 'vehicle_id', 'id');
    }
}
