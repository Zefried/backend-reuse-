<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CottageBooking extends Model
{
    use HasFactory;

    protected $table = 'cottage_bookings';

    protected $fillable = [
        'cottage_id',
        'cottage_name',
        'guest_name',
        'phone',
        'email',
        'arrival_date',
        'nights',
        'guests_count',
        'special_requests',
        'status',
        'price_quoted',
        'admin_notes',
        'last_contacted_at',
    ];

    protected $casts = [
        'arrival_date' => 'date',
        'last_contacted_at' => 'datetime',
        'price_quoted' => 'decimal:2',
    ];

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeFilter($query, array $filters)
    {
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('guest_name', 'like', $search)
                  ->orWhere('phone', 'like', $search)
                  ->orWhere('email', 'like', $search)
                  ->orWhere('cottage_name', 'like', $search);
            });
        }

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['cottage_id']) && $filters['cottage_id'] !== 'all') {
            $query->where('cottage_id', $filters['cottage_id']);
        }
    }
}
