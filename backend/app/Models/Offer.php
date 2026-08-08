<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Offer extends Model
{
    protected $fillable = [
        'category_id',
        'title',
        'description',
        'type',
        'price',
        'is_negotiable',
        'status',
        'location',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

     public function scopeWithLocationCoordinates($query)
    {
        return $query
            ->select('offers.*')
            ->selectRaw('ST_Y(location::geometry) AS latitude')
            ->selectRaw('ST_X(location::geometry) AS longitude');
    }
}
