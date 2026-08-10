<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    protected $fillable = [
        'user_id',
        'offer_id',
        'scheduled_at',
        'status',
        'notes',
        'cancelled_at',
        'cancelled_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'cancelled_at' => 'datetime',
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

}
