<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleTaxonomyFetch extends Model
{
    protected $table = 'vehicle_taxonomy_fetches';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['fetched_at' => 'datetime'];
    }
}
