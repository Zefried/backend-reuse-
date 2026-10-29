<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VisitEnquiry extends Model
{
    use HasFactory;

    protected $table = 'visit_enquiries';

    protected $fillable = [
        'name',
        'contact',
        'visit_date',
        'message',
        'status',
        'admin_notes',
        'last_contacted_at',
    ];

    protected $casts = [
        'visit_date' => 'date',
        'last_contacted_at' => 'datetime',
    ];

    public function scopeNew($query)
    {
        return $query->where('status', 'new');
    }

    public function scopeFilter($query, array $filters)
    {
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                  ->orWhere('contact', 'like', $search)
                  ->orWhere('message', 'like', $search);
            });
        }

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }
    }
}
