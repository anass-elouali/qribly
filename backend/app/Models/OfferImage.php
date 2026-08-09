<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferImage extends Model
{
    protected   $fillable = [
        'offer_id',
        'path',
    ];

     public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }
}
