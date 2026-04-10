<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Doc extends Model
{
    use HasFactory, SoftDeletes;
    protected $softDelete = true;
    protected $hidden = ['deleted_at'];

    public function documentType()
    {
        return $this->belongsTo(Document_type::class);
    }

    public function engineer()
    {
        return $this->belongsTo(Employees::class, 'engineer_id');
    }

    public function insuranceName()
    {
        return $this->belongsTo(Insurance_name::class, 'insurance_names_id');
    }

    public function docCustomers()
    {
        return $this->hasMany(DocCustomer::class, 'docs_id');
    }
    public function Services()
    {
        return $this->hasMany(Services::class,'doc_id');
    }


}
