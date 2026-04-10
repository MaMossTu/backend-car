<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Save_document extends Model
{
    use HasFactory, SoftDeletes;

    protected $softDelete = true;
    protected $hidden = ['deleted_at'];
    protected $appends = ['picture_url'];

    public function inspections()
    {
        return $this->belongsTo(Inspections::class, 'inspection_id', 'id');
    }

    public function getDocumentUrlAttribute()
    {
        return $this->document ? asset($this->document) : null;
    }



}

