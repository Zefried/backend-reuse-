<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    protected $fillable = [
        'attachable_id',
        'attachable_type',
        'type',
        'title',
        'url',
        'file_name',
        'file_size',
        'file_hash',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'file_size' => 'integer',
    ];

    /**
     * Get the parent attachable model (AiContext, etc.).
     */
    public function attachable()
    {
        return $this->morphTo();
    }
}
