<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Insurance_link_phone extends Model
{
    use HasFactory, SoftDeletes;
    protected $softDelete = true;
    protected $hidden = ['deleted_at'];

    public function Branches(){

        return $this->belongsTo(Branches::class,'branch_id','id');
    }
    public function Insurance_names(){

        return $this->belongsTo(Insurance_name::class,'insurance_name_id','id');
    }

    public function inspections(){

        return $this->hasmany(Inspections::class,'insurance_link_phone_id','id');
    }
}
