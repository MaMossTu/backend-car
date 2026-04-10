<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quotation extends Model
{
    use HasFactory, SoftDeletes;
    protected $softDelete = true;
    protected $hidden = ['deleted_at'];

    public function Branches()
    {
        return $this->belongsTo(Branches::class, 'branche_id');
    }
    public function Customers()
    {
        return $this->belongsTo(Customers::class, 'customer_id');
    }
    public function quotationServices()
    {
        return $this->hasMany(QuotationService::class);
    }
    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'quotation_id', 'id');
    }
}
