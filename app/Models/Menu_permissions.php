<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Menu_permissions extends Model
{
    use HasFactory,SoftDeletes;
    protected $softDelete = true;

    protected $hidden = ['deleted_at'];

    public function permissions()
    {
        return $this->belongsTo(permissions::class,'permission_id','id');
    }
    public function menu()
    {
        return $this->belongsTo(Menu::class,'menu_id','id');
    }
    public function roles()
    {
        return $this->belongsTo(Roles::class,'role_id','id');
    }
}
