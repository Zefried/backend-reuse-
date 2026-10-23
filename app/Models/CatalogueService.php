<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatalogueService extends Model
{
    use HasFactory;

    protected $table = 'catalogue_services';

    protected $fillable = [
        'business_id',
        'service_name',
    ];

    public function business()
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    public function categories()
    {
        return $this->hasMany(CatalogueCategory::class, 'service_id');
    }

    public function rootCategories()
    {
        return $this->hasMany(CatalogueCategory::class, 'service_id')->whereNull('parent_id');
    }

    public function designs()
    {
        return $this->hasMany(Design::class, 'service_id');
    }
}
