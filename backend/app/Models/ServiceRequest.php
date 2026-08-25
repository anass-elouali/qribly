<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ServiceRequest extends Model
{
    protected $attributes = [
        'status' => 'open',
        'at_home' => true,
    ];

    protected $fillable = [
        'category_id',
        'raw_text',
        'summary',
        'city',
        'location',
        'desired_start_at',
        'desired_end_at',
        'budget_max',
        'at_home',
        'status',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'desired_start_at' => 'datetime',
            'desired_end_at' => 'datetime',
            'budget_max' => 'decimal:2',
            'at_home' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(ServiceRequestProposal::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(ServiceRequestMatch::class);
    }

    public function acceptedProposal(): HasOne
    {
        return $this->hasOne(ServiceRequestProposal::class)
            ->where('status', 'accepted');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open' && $this->expires_at->isFuture();
    }
}
