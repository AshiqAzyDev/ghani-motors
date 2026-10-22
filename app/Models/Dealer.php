<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dealer extends Model
{
    protected $table = 'dealers';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['on_integration' => 'boolean'];
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function whatsappNumber(): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->phone) ?? '';
        if ($digits === '' || in_array($digits, ['02000000000', '442000000000', '440200000000'], true)) {
            return null;
        }
        if (str_starts_with($digits, '0')) {
            $digits = '44'.substr($digits, 1);
        }
        if (! str_starts_with($digits, '44') || strlen($digits) < 11) {
            return null;
        }

        return $digits;
    }

    public static function current(): self
    {
        $host = request()->getHost();

        return static::query()->where('website_host', $host)->first()
            ?? static::query()->orderBy('id')->firstOrFail();
    }
}
