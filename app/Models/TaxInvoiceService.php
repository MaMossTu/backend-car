<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxInvoiceService extends Model
{
    use HasFactory, SoftDeletes;
    protected $softDelete = true;
    protected $hidden = ['deleted_at'];

    public function TaxInvoice()
    {
        return $this->belongsTo(TaxInvoice::class, 'tax_invoice_id');
    }

    public function Services()
    {
        return $this->belongsTo(Services::class, 'service_id');
    }


}
