<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ValuationPhoto extends Model
{
    protected $table = 'valuation_photos';

    public const UPDATED_AT = null;

    protected $guarded = [];

    public function valuation(): BelongsTo
    {
        return $this->belongsTo(Valuation::class);
    }
}
