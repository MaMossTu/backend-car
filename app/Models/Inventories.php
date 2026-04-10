<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inventories extends Model
{
    use HasFactory, SoftDeletes;
    protected $softDelete = true;
    protected $hidden = ['deleted_at'];

    public function Vendors()
    {
        return $this->belongsTo(Vendors::class);
    }
    public function LineItems()
    {
        return $this->hasMany(LineItems::class);
    }
 
}
