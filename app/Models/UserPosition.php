<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class UserPosition extends Model
{
    use HasFactory,SoftDeletes;
    protected $softDelete = true;
    protected $hidden = ['deleted_at'];

    public function Roles(){
        return $this->belongsTo(Roles::class, 'role_id');
    }
    public function Users(){
        return $this->belongsTo(User::class, 'user_id');
    }
    public function Department(){
        return $this->belongsTo(Department::class, 'user_id');
    }
    public function SubDepartment(){
        return $this->belongsTo(SubDepartment::class, 'user_id');
    }


}
