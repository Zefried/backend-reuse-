<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DesignSpecification extends Model
{
    use HasFactory;

    protected $table = 'design_specifications';

    protected $fillable = [
        'design_id',
        'attribute_name',
        'attribute_value',
    ];

    public function design()
    {
        return $this->belongsTo(Design::class, 'design_id');
    }
}
