<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkOrderServices extends Model
{
    use HasFactory, SoftDeletes;
    protected $softDelete = true;
    protected $hidden = ['deleted_at'];
    protected $fillable = [
        'work_order_id',
        'service_id',
        'qty',
        'service_name',
        'service_price',
        'unit_price',
        'discount',
    ];

    public function repair_service()
    {
        return $this->belongsTo(Repair_service::class, 'repair_service_id');
    }
    public function WorkOrder()
    {
        return $this->belongsTo(WorkOrder::class, 'work_order_id');
    }
    public function Service()
    {
        return $this->belongsTo(Services::class, 'service_id');
    }
}
