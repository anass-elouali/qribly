<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    protected $fillable = [
        'user_id',
        'offer_id',
        'service_request_id',
        'service_request_proposal_id',
        'scheduled_at',
        'duration_minutes',
        'agreed_price',
        'status',
        'notes',
        'cancelled_at',
        'cancelled_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'duration_minutes' => 'integer',
        'agreed_price' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function serviceRequestProposal(): BelongsTo
    {
        return $this->belongsTo(ServiceRequestProposal::class);
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }
}
