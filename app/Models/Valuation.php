<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Valuation extends Model
{
    protected $table = 'valuations';

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'mot_expiry' => 'date',
            'accepted_terms_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ValuationPhoto::class)->orderBy('sort_order');
    }
}
