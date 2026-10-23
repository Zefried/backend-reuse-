<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Niche extends Model
{
    use HasFactory;

    protected $table = 'niches';

    protected $fillable = [
        'niche_name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function businesses()
    {
        return $this->hasMany(User::class, 'niche_id')->where('role', 'business');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
