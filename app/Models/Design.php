<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Design extends Model
{
    use HasFactory;

    protected $table = 'designs';

    protected $fillable = [
        'business_id',
        'service_id',
        'category_id',
        'design_name',
    ];

    public function business()
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    public function service()
    {
        return $this->belongsTo(CatalogueService::class, 'service_id');
    }

    public function category()
    {
        return $this->belongsTo(CatalogueCategory::class, 'category_id');
    }

    public function specifications()
    {
        return $this->hasMany(DesignSpecification::class, 'design_id');
    }
}
