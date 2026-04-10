<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoiceService extends Model
{
    use HasFactory, SoftDeletes;
    protected $softDelete = true;
    protected $hidden = ['deleted_at'];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function Services()
    {
        return $this->belongsTo(Services::class, 'service_id');
    }
}
