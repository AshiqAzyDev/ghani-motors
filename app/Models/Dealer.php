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

    public function showroomAddress(): string
    {
        return trim(implode(', ', array_filter([
            $this->address_line ?: 'Rainham Road South',
            $this->city ?: 'Dagenham',
            $this->postcode ?: 'RM10 8YT',
        ])));
    }

    public function mapsSearchUrl(): string
    {
        return 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($this->showroomAddress());
    }

    public function mapsDirectionsUrl(): string
    {
        return 'https://www.google.com/maps/dir/?api=1&destination='.rawurlencode($this->showroomAddress());
    }

    /**
     * Embed URL using lat/lng so Google does not draw its own place card
     * on top of (or under) our custom map overlay.
     */
    public function mapsEmbedUrl(int $zoom = 15): string
    {
        [$lat, $lng] = $this->showroomCoordinates();

        return 'https://maps.google.com/maps?q='.rawurlencode($lat.','.$lng).'&z='.$zoom.'&hl=en&output=embed';
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function showroomCoordinates(): array
    {
        // Rainham Road South, Dagenham RM10 8YT — pin only, no Google place card.
        return ['51.5405', '0.1544'];
    }

    public function logoUrl(): ?string
    {
        return $this->publicAssetUrl($this->logo_path);
    }

    public function faviconUrl(): ?string
    {
        return $this->publicAssetUrl($this->favicon_path);
    }

    public function displayName(): string
    {
        return $this->trading_name ?: $this->name ?: 'Ghani Motors';
    }

    public function displayTagline(): string
    {
        return $this->tagline ?: 'QUALITY USED CARS';
    }

    public function publicEmail(): ?string
    {
        $email = trim((string) $this->email);

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    private function publicAssetUrl(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        return asset('storage/'.$path);
    }

    public static function current(): self
    {
        $host = request()->getHost();

        return static::query()->where('website_host', $host)->first()
            ?? static::query()->orderBy('id')->firstOrFail();
    }
}
