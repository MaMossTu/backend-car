<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Roles extends Model
{
    use HasFactory,SoftDeletes;
    protected $softDelete = true;
    protected $hidden = ['deleted_at'];

    public function RolePermissions(){
        return $this->hasMany(RolePermissions::class, 'role_id');
    }

    public function RoleMenus(){
        return $this->hasMany(MenuRole::class, 'role_id');
    }

    public function UserPosition(){
        return $this->hasMany(UserPosition::class);
    }

    public function userPositions()
    {
        return $this->belongsTo(UserPosition::class);
    }

    public function menu_permissions(){
        return $this->hasMany(Menu_permissions::class, 'role_id');
    }

}
