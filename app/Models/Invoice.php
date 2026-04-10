<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;
    protected $softDelete = true;
    protected $hidden = ['deleted_at'];

    public function Branches()
    {
        return $this->belongsTo(Branches::class, 'branche_id');
    }

    public function quotation()
    {
        return $this->belongsTo(Quotation::class, 'quotation_id');
    }

    public function invoiceServices()
    {
        return $this->hasMany(InvoiceService::class);
    }
    public function invoicePayment()
    {
        return $this->hasMany(InvoicePayment::class);
    }
    public function billingInvoice()
    {
        return $this->hasMany(BillingInvoice::class);
    }

    public function Customers()
    {
        return $this->belongsTo(Customers::class, 'customer_id', 'id');
    }
}
