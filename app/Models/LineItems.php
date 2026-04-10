<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LineItems extends Model
{
    use HasFactory, SoftDeletes;
    protected $softDelete = true;
    protected $hidden = ['deleted_at'];

    public function Inventorys()
    {
        return $this->belongsTo(Inventories::class);
    }

    public function WorkOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }
    public function Employee()
    {
        return $this->belongsTo(Employees::class);
    }
    public function Item()
    {
        return $this->belongsTo(Item::class);
    }
    public function service()
    {
        return $this->belongsTo(Services::class);
    }
}
