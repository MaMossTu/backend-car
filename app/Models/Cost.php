<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cost extends Model
{
    use HasFactory, SoftDeletes;
    protected $softDelete = true;
    protected $hidden = ['deleted_at'];
    public function Branches(){
        return $this->belongsTo(Branches::class, 'branches_id');
    }

    public function ExpenseType(){
        return $this->belongsTo(ExpenseType::class, 'expense_type_id');
    }

    public function Employees(){
        return $this->belongsTo(Employees::class, 'employees_id');
    }

}

