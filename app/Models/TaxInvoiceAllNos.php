<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxInvoiceAllNos extends Model
{
    use HasFactory, SoftDeletes;
    protected $softDelete = true;
    protected $hidden = ['deleted_at'];

    public function Inspection()
    {
        return $this->belongsTo(Inspections::class, 'inspection_id');
    }
    public function TaxInvoice()
    {
        return $this->belongsTo(TaxInvoice::class, 'tax_invoice_id');
    }
}
