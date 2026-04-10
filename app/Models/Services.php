<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Services extends Model
{
    use HasFactory, SoftDeletes;
    protected $softDelete = true;
    protected $hidden = ['deleted_at'];
    protected $appends = ['image_url'];

    protected $casts = [
        'is_vat' => 'boolean'
    ];

    public function ServiceTransactions()
    {
        return $this->hasMany(ServiceTransactions::class, 'service_id', 'id');
    }
    public function PackageServices()
    {
        return $this->hasMany(PackageServices::class);
    }
    public function WorkOrderServices()
    {
        return $this->hasMany(WorkOrderServices::class);
    }
    public function commissions()
    {
        return $this->belongsTo(Commission::class, 'service_id', 'id');
    }
    public function ServiceDocTypes()
    {
        return $this->hasMany(ServiceDocType::class);
    }

    public function TaxInvoiceService()
    {
        return $this->hasMany(TaxInvoiceService::class);
    }
    public function getImageUrlAttribute()
    {
        return $this->image ? asset($this->image) : null;
    }
    public function ProductType()
    {
        return $this->belongsTo(ProductType::class, 'product_type_id', 'id');
    }
    public function Doc()
    {
        return $this->belongsTo(Doc::class, 'doc_id', 'id');
    }
    public function serviceType()
    {
        return $this->belongsTo(ServiceType::class, 'service_type_id', 'id');
    }
}
