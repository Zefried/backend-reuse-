<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatalogueCategory extends Model
{
    use HasFactory;

    protected $table = 'catalogue_categories';

    protected $fillable = [
        'business_id',
        'service_id',
        'category_name',
        'parent_id',
    ];

    public function business()
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    public function service()
    {
        return $this->belongsTo(CatalogueService::class, 'service_id');
    }

    public function parent()
    {
        return $this->belongsTo(CatalogueCategory::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(CatalogueCategory::class, 'parent_id');
    }

    public function allChildren()
    {
        return $this->hasMany(CatalogueCategory::class, 'parent_id')->with('allChildren');
    }

    public function designs()
    {
        return $this->hasMany(Design::class, 'category_id');
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeForService($query, int $serviceId)
    {
        return $query->where('service_id', $serviceId);
    }
}
