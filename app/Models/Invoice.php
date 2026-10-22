<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $table = 'invoices';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'customer_snapshot' => 'array',
            'vehicle_snapshot' => 'array',
            'prices_include_vat' => 'boolean',
            'issued_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('sort_order');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function related(): BelongsTo
    {
        return $this->belongsTo(self::class, 'related_invoice_id');
    }

    public function paidPence(): int
    {
        return (int) $this->payments()->sum('amount_pence');
    }

    public function balancePence(): int
    {
        return (int) $this->total_pence - $this->paidPence();
    }
}
