<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Employees extends Model
{
    use HasFactory,SoftDeletes;
    protected $softDelete = true;
    protected $hidden = ['deleted_at'];

    public function Branches(){
        return $this->belongsTo(Branches::class,'branch_id','id');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function inspections()
    {
        return $this->hasMany(Inspections::class, 'employee_id');
    }

    public function LineItem()
    {
        return $this->hasMany(LineItems::class);
    }

    public function docs()
    {
        return $this->hasMany(Doc::class, 'engineer_id');
    }

    public function Cost()
    {
        return $this->hasMany(Cost::class);
    }
}
