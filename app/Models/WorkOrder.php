<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkOrder extends Model
{
    use HasFactory, SoftDeletes;
    protected $softDelete = true;
    protected $hidden = ['deleted_at'];
    protected $fillable = [
        'no',
        'remark',
        'total',
        'date',
        'vehicle_id',
        'branches_id',
        'status',
        'status_paid',
        'customer_id',
        'customer_no',
        'customer_name',
        'customer_address',
        'customer_phone',
        'customer_email',
        'customer_tax_id',
        'customer_is_headquarter',
        'brand_name',
        'model_name',
        'license_plate',
        'mileage',
        'chassis_number',
        'engine_number',
        'tax',
        'discount',
        'total_amount',
        'type',
    ];
    public function Vehicles()
    {
        return $this->belongsTo(Vehicles::class, 'vehicle_id');
    }
    public function WorkOrderServices()
    {
        return $this->hasMany(WorkOrderServices::class, 'work_order_id');
    }
    public function LineItems()
    {
        return $this->hasMany(LineItems::class);
    }

    public function Customer()
    {
        return $this->belongsTo(Customers::class, 'customer_id');
    }
    public function Branches()
    {
        return $this->belongsTo(Branches::class, 'branches_id');
    }
    public function WorkOrderPayment()
    {
        return $this->hasMany(WorkOrderPayment::class);
    }
}
