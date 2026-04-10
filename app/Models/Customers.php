<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Customers extends Model
{
    use HasFactory,SoftDeletes;
    protected $softDelete = true;
    protected $hidden = ['deleted_at'];

    protected $fillable = [
        'no',
        'name',
        'email',
        'phone_number1',
        'phone_number2',
        'tax_id',
        'type',
        'is_headquarter'
    ];

    public function Customer_Addresses(){
        return $this->hasMany(CustomerAddresses::class, 'customer_id');
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicles::class, 'customer_id', 'id');
    }

    public function Inspections(){
        return $this->hasMany(Inspections::class, 'customer_id');
    }

    public function WorkOrders(){
        return $this->hasMany(WorkOrder::class,'customer_id', 'id');
    }

    public function docs()
    {
        return $this->hasMany(Doc::class);
    }
    public function taxInvoice()
    {
        return $this->hasMany(TaxInvoice::class, 'branche_id');
    }
    public function quotation()
    {
        return $this->hasMany(Quotation::class, 'branche_id');
    }
}
