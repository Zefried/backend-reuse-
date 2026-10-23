<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'business_id',
        'niche_id',
        'category_id',
        'name',
        'email',
        'phone',
        'bio',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function niche()
    {
        return $this->belongsTo(Niche::class, 'niche_id');
    }

    public function catalogueServices()
    {
        return $this->hasMany(CatalogueService::class, 'business_id');
    }

    public function catalogueCategories()
    {
        return $this->hasMany(CatalogueCategory::class, 'business_id');
    }

    public function designs()
    {
        return $this->hasMany(Design::class, 'business_id');
    }

    public function getBusinessNameAttribute()
    {
        return $this->name;
    }
}
