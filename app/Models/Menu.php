<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Menu extends Model
{
    use HasFactory,SoftDeletes;
    protected $softDelete = true;
    protected $hidden = ['deleted_at'];

    public function menu_permissions()
    {
        return $this->hasMany(Menu_permissions::class);
    }

    public function menu_role()
    {
        return $this->hasMany(MenuRole::class);
    }

}
