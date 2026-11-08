<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Branches extends Model
{
    use HasFactory,SoftDeletes;
    protected $appends = ['logo_url','logo_under_url'];
    protected $softDelete = true;
    protected $hidden = ['deleted_at'];

    public function Employees(){
        return $this->hasMany(Employees::class);
    }
    public function Inspections(){
        return $this->hasMany(Inspections::class);
    }
    public function payout_commissions(){

        return $this->belongsTo(payout_commissions::class,'employee_id','id');
    }
    public function Provinces(){

        return $this->belongsTo(Provinces::class,'province_id','id');
    }
    public function Districts(){

        return $this->belongsTo(Districts::class,'district_id','id');
    }
    public function SubDistricts(){

        return $this->belongsTo(SubDistricts::class,'subDistrict_id','id');
    }
    public function getLogoUrlAttribute()
    {
        if (!$this->logo) {
            return null;
        }
        $v = $this->updated_at ? $this->updated_at->getTimestamp() : time();

        return asset($this->logo) . '?t=' . $v;
    }
    public function getLogoUnderUrlAttribute()
    {
        if (!$this->logo_under) {
            return null;
        }
        $v = $this->updated_at ? $this->updated_at->getTimestamp() : time();

        return asset($this->logo_under) . '?t=' . $v;
    }
    // public function getLogoUrlAttribute($value)
    // {
    //     return ($value ? url($value) : null);
    // }
    public function WorkOrder()
    {
        return $this->hasMany(WorkOrder::class, 'branches_id');
    }
    public function Cost()
    {
        return $this->hasMany(Cost::class, 'branches_id');
    }

    public function insurance_link_phone()
    {
        return $this->hasMany(Insurance_link_phone::class,'branch_id','id');
    }
    public function quotation()
    {
        return $this->hasMany(Quotation::class, 'branche_id');
    }
    public function taxInvoice()
    {
        return $this->hasMany(TaxInvoice::class, 'branche_id');
    }
}
