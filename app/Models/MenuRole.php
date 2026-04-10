<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class MenuRole extends Model
{
    use HasFactory,SoftDeletes;
    protected $table = 'role_menu';
    protected $softDelete = true;
    protected $hidden = ['deleted_at'];
    public function Permissions(){
        return $this->belongsTo(Permissions::class,'permission_id');
    }
    public function Role(){
        return $this->belongsTo(Roles::class, 'role_id');
    }

    public function menu()
    {
        return $this->belongsTo(Menu::class,'menu_id','id');

}

}
