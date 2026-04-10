<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Doc_transaction extends Model
{
    use HasFactory, SoftDeletes;
    protected $softDelete = true;
    protected $hidden = ['deleted_at'];

    public function docCustomers()
    {
        return $this->belongsTo(DocCustomer::class, 'doc_customer_id');
    }
    public function doc()
    {
        return $this->belongsTo(Doc::class, 'doc_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customers::class, 'customer_id');
    }

    public function inspection()
    {
        return $this->belongsTo(Inspections::class, 'inspection_id');
    }

    public function branches()
    {
        return $this->belongsTo(Branches::class, 'branch_id');
    }
}
