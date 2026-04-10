<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceDocType extends Model
{
    use HasFactory, SoftDeletes;
    protected $softDelete = true;
    protected $hidden = ['deleted_at'];

    public function documentType()
    {
        return $this->belongsTo(Document_type::class, 'document_types_id');
    }

    public function service()
    {
        return $this->belongsTo(Services::class, 'services_id');
    }
}
