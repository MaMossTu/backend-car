<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item_type extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'item_types';
    protected $softDelete = true;
    protected $hidden = ['deleted_at'];
    public function item()
    {
        return $this->hasMany(Item::class,'`item_type_id`','id');
    }
}
