<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Lead extends Model
{
    protected $table = 'leads';

    public const UPDATED_AT = null;

    public const REQUIREMENT_KEYS = [
        'year' => 'Year',
        'mileage' => 'Mileage',
        'fuel' => 'Fuel',
        'transmission' => 'Gearbox',
        'colour' => 'Colour',
        'body' => 'Body',
        'budget' => 'Budget',
        'doors' => 'Doors',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'accepted_terms_at' => 'datetime',
            'contacted_at' => 'datetime',
            'created_on' => 'date',
            'requirements' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'assigned_to');
    }

    public function valuation(): HasOne
    {
        return $this->hasOne(Valuation::class);
    }

    public function requirementPairs(): array
    {
        $rows = [];
        foreach ((array) $this->requirements as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $rows[] = [
                'key' => (string) $key,
                'label' => self::REQUIREMENT_KEYS[$key] ?? ucfirst(str_replace('_', ' ', (string) $key)),
                'value' => (string) $value,
            ];
        }

        return $rows;
    }
}
