<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxInvoice extends Model
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
    public function taxInvoiceService()
    {
        return $this->hasMany(TaxInvoiceService::class);
    }
    public function taxInvoiceAllNos()
    {
        return $this->hasMany(TaxInvoiceAllNos::class, 'tax_invoice_id', 'id');
    }

    public function Invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
    public function WorkOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }
}
