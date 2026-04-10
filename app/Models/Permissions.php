<?php

namespace App\Models;

use App\Http\Controllers\MenusPermissionsController;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Permissions extends Model
{
    use HasFactory,SoftDeletes;
    protected $softDelete = true;
    protected $hidden = ['deleted_at'];


    public function RolePermissions(){
        return $this->hasMany(RolePermissions::class,'rolePermission_id');
    }

    public function menu_permissions(){
        return $this->hasMany(Menu_permissions::class, 'menu_permission_id');
    }

}
