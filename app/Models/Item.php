<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use HasFactory, SoftDeletes;
    protected $softDelete = true;
    protected $hidden = ['deleted_at'];
    protected $appends = ['image_url'];
    public function Lineitem()
    {
        return $this->hasMany(LineItems::class);
    }
    public function itemType()
{
    return $this->belongsTo(Item_type::class, 'item_type_id', 'id');
}
    public function vendor()
    {
        return $this->belongsTo(Vendors::class);
    }
    public function getImageUrlAttribute()
    {
        return $this->image ? asset($this->image) : null;
    }

}
