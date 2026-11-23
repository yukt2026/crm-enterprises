<?php

namespace App\Domains\HR\Models;

use App\Domains\Tenancy\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePerformanceReview extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'reviewer_id',
        'review_period',
        'review_date',
        'kpi_scores',
        'overall_rating',
        'strengths',
        'improvements',
        'goals',
        'manager_feedback',
        'status',
    ];

    protected $casts = [
        'review_date' => 'date',
        'kpi_scores' => 'array',
        'overall_rating' => 'decimal:2',
    ];

    protected $appends = ['rating_overall'];

    public function getRatingOverallAttribute()
    {
        return (float) $this->overall_rating;
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
