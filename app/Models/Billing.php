<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Billing extends Model
{
    use HasFactory, SoftDeletes;
    protected $softDelete = true;
    protected $hidden = ['deleted_at'];

    public function Branches()
    {
        return $this->belongsTo(Branches::class, 'branche_id');
    }
    public function billingInvoice()
    {
        return $this->hasMany(BillingInvoice::class);
    }
}
