<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document_type extends Model
{
    use HasFactory, SoftDeletes;
    protected $softDelete = true;
    protected $hidden = ['deleted_at'];

    public function docs()
    {
        return $this->hasMany(Doc::class);
    }

    public function ServiceDocTypes()
    {
        return $this->hasMany(ServiceDocType::class);
    }
}
