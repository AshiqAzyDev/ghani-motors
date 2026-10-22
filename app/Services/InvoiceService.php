<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\InvoicePayment;
use App\Models\Sale;
use App\Models\StaffUser;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function __construct(private VehicleStatus $status, private CustomerDirectory $directory) {}

    public function completeSale(Dealer $dealer, Vehicle $vehicle, Customer $customer, array $data, StaffUser $staff): Sale
    {
        return DB::transaction(function () use ($dealer, $vehicle, $customer, $data, $staff) {
            $vehicle = Vehicle::query()->where('dealer_id', $dealer->id)->lockForUpdate()->findOrFail($vehicle->id);

            $held = Booking::query()
                ->where('vehicle_id', $vehicle->id)
                ->where('type', 'reservation')
                ->where('status', 'held')
                ->first();

            if ($held && (int) $held->customer_id !== (int) $customer->id) {
                throw ValidationException::withMessages([
                    'customer_id' => 'This car is reserved for another customer.',
                ]);
            }

            if (Sale::query()->where('vehicle_id', $vehicle->id)->where('status', 'completed')->exists()) {
                throw ValidationException::withMessages([
                    'vehicle_id' => 'This car already has a completed sale.',
                ]);
            }

            $sale = Sale::query()->create([
                'vehicle_id' => $vehicle->id,
                'customer_id' => $customer->id,
                'part_exchange_valuation_id' => $data['part_exchange_valuation_id'] ?? null,
                'vehicle_label' => $vehicle->title() ?: 'Vehicle '.$vehicle->id,
                'registration' => $vehicle->registration,
                'price_pence' => (int) $data['price_pence'],
                'sold_on' => $data['sold_on'],
                'method' => $data['method'],
                'status' => 'completed',
                'notes' => $data['notes'] ?? null,
                'created_by' => $staff->id,
            ]);

            Booking::query()
                ->where('vehicle_id', $vehicle->id)
                ->where('type', 'reservation')
                ->where('status', 'held')
                ->update(['status' => 'completed', 'updated_by' => $staff->id]);

            Booking::query()
                ->where('vehicle_id', $vehicle->id)
                ->whereIn('type', ['test_drive', 'viewing'])
                ->whereIn('status', ['requested', 'confirmed'])
                ->update(['status' => 'cancelled', 'updated_by' => $staff->id]);

            $vehicle->local_hold = 'none';
            $vehicle->website_status = 'sold';
            $vehicle->show_on_website = false;
            $vehicle->updated_by = $staff->id;
            $vehicle->save();

            $this->directory->log($staff->id, $vehicle->id, $customer->id, 'Sale recorded for '.$vehicle->title());

            return $sale;
        });
    }

    public function createInvoice(Dealer $dealer, array $data, array $lines, StaffUser $staff): Invoice
    {
        return DB::transaction(function () use ($dealer, $data, $lines, $staff) {
            $customer = Customer::query()->where('dealer_id', $dealer->id)->findOrFail($data['customer_id']);
            $sale = null;
            if (! empty($data['sale_id'])) {
                $sale = Sale::query()->whereHas('vehicle', fn ($q) => $q->where('dealer_id', $dealer->id))->findOrFail($data['sale_id']);
                if (Invoice::query()->where('sale_id', $sale->id)->exists()) {
                    throw ValidationException::withMessages(['sale_id' => 'That sale already has an invoice.']);
                }
            }

            $built = $this->buildLines($data['vat_scheme'], (bool) $data['prices_include_vat'], $lines);
            $invoice = Invoice::query()->create([
                'sale_id' => $sale?->id,
                'number' => $this->directory->nextInvoiceNumber($dealer),
                'kind' => 'invoice',
                'customer_id' => $customer->id,
                'customer_snapshot' => $this->customerSnapshot($customer),
                'vehicle_snapshot' => $sale ? [
                    'label' => $sale->vehicle_label,
                    'registration' => $sale->registration,
                    'vat_status' => $sale->vehicle?->vat_status,
                ] : ['label' => $data['vehicle_label'] ?? ''],
                'vat_scheme' => $data['vat_scheme'],
                'prices_include_vat' => (bool) $data['prices_include_vat'],
                'net_pence' => $built['net'],
                'vat_pence' => $built['vat'],
                'total_pence' => $built['total'],
                'vat_rate_bps' => $built['rate'],
                'status' => 'draft',
                'created_by' => $staff->id,
            ]);

            foreach ($built['lines'] as $index => $line) {
                InvoiceLine::query()->create(['invoice_id' => $invoice->id, 'sort_order' => $index] + $line);
            }

            $this->directory->log($staff->id, $sale?->vehicle_id, $customer->id, 'Draft invoice '.$invoice->number.' created');

            return $invoice;
        });
    }

    public function issue(Invoice $invoice, StaffUser $staff): void
    {
        if ($invoice->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Only a draft can be issued.']);
        }
        $invoice->status = 'issued';
        $invoice->issued_at = now();
        $invoice->save();
        $this->directory->log($staff->id, null, $invoice->customer_id, 'Invoice '.$invoice->number.' issued');
    }

    public function addPayment(Invoice $invoice, int $amount, string $date, string $method, string $reference, StaffUser $staff): void
    {
        if ($invoice->kind === 'credit_note') {
            throw ValidationException::withMessages(['amount' => 'Credit notes do not take payments.']);
        }
        if (! in_array($invoice->status, ['issued', 'part_paid'], true)) {
            throw ValidationException::withMessages(['amount' => 'Payments can be recorded on issued invoices.']);
        }
        if ($amount > $invoice->balancePence()) {
            throw ValidationException::withMessages(['amount' => 'That payment is more than the balance.']);
        }

        InvoicePayment::query()->create([
            'invoice_id' => $invoice->id,
            'amount_pence' => $amount,
            'received_on' => $date,
            'method' => $method,
            'reference' => $reference,
            'recorded_by' => $staff->id,
        ]);

        $balance = $invoice->balancePence();
        $invoice->status = $balance <= 0 ? 'paid' : 'part_paid';
        $invoice->save();
        $this->directory->log($staff->id, null, $invoice->customer_id, 'Payment recorded on '.$invoice->number);
    }

    public function void(Invoice $invoice, StaffUser $staff): void
    {
        if ($invoice->payments()->exists()) {
            throw ValidationException::withMessages(['status' => 'Void is only available when no payments remain.']);
        }
        if (! in_array($invoice->status, ['draft', 'issued'], true)) {
            throw ValidationException::withMessages(['status' => 'This invoice cannot be voided.']);
        }
        $invoice->status = 'void';
        $invoice->voided_at = now();
        $invoice->save();
        $this->directory->log($staff->id, null, $invoice->customer_id, 'Invoice '.$invoice->number.' voided');
    }

    public function credit(Dealer $dealer, Invoice $invoice, StaffUser $staff): Invoice
    {
        if ($invoice->kind !== 'invoice' || ! in_array($invoice->status, ['issued', 'part_paid', 'paid'], true)) {
            throw ValidationException::withMessages(['status' => 'This invoice cannot be credited.']);
        }
        if (Invoice::query()->where('related_invoice_id', $invoice->id)->where('kind', 'credit_note')->exists()) {
            throw ValidationException::withMessages(['status' => 'A credit note already exists.']);
        }

        return DB::transaction(function () use ($dealer, $invoice, $staff) {
            $credit = Invoice::query()->create([
                'number' => $this->directory->nextInvoiceNumber($dealer),
                'kind' => 'credit_note',
                'related_invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'customer_snapshot' => $invoice->customer_snapshot,
                'vehicle_snapshot' => $invoice->vehicle_snapshot,
                'vat_scheme' => $invoice->vat_scheme,
                'prices_include_vat' => $invoice->prices_include_vat,
                'net_pence' => -$invoice->net_pence,
                'vat_pence' => -$invoice->vat_pence,
                'total_pence' => -$invoice->total_pence,
                'vat_rate_bps' => $invoice->vat_rate_bps,
                'status' => 'issued',
                'issued_at' => now(),
                'created_by' => $staff->id,
            ]);

            foreach ($invoice->lines as $line) {
                InvoiceLine::query()->create([
                    'invoice_id' => $credit->id,
                    'sort_order' => $line->sort_order,
                    'line_type' => $line->line_type,
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'unit_net_pence' => -$line->unit_net_pence,
                    'vat_rate_bps' => $line->vat_rate_bps,
                    'vat_pence' => -$line->vat_pence,
                    'line_total_pence' => -$line->line_total_pence,
                ]);
            }

            $invoice->status = 'credited';
            $invoice->save();
            $this->directory->log($staff->id, null, $invoice->customer_id, 'Credit note '.$credit->number.' issued');

            return $credit;
        });
    }

    private function customerSnapshot(Customer $customer): array
    {
        return [
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'address' => $customer->address,
            'postcode' => $customer->postcode,
        ];
    }

    private function buildLines(string $scheme, bool $pricesIncludeVat, array $lines): array
    {
        $rate = $scheme === 'standard' ? 2000 : 0;
        $net = 0;
        $vat = 0;
        $total = 0;
        $built = [];

        foreach ($lines as $line) {
            $description = trim((string) ($line['description'] ?? ''));
            if ($description === '') {
                continue;
            }
            $quantity = (float) ($line['quantity'] ?? 1);
            if ($quantity <= 0) {
                $quantity = 1;
            }
            $unit = \App\Support\Money::poundsToPence($line['amount'] ?? 0);
            $sign = in_array($line['line_type'] ?? '', ['part_exchange', 'discount'], true) ? -1 : 1;
            $unit *= $sign;

            if ($scheme === 'standard' && $pricesIncludeVat) {
                $unitGross = $unit;
                $unitNet = (int) round($unitGross * 10000 / (10000 + $rate));
                $lineVat = (int) round(($unitGross - $unitNet) * $quantity);
                $lineNet = (int) round($unitNet * $quantity);
                $lineTotal = (int) round($unitGross * $quantity);
            } elseif ($scheme === 'standard') {
                $unitNet = $unit;
                $lineNet = (int) round($unitNet * $quantity);
                $lineVat = (int) round($lineNet * $rate / 10000);
                $lineTotal = $lineNet + $lineVat;
            } else {
                $unitNet = $unit;
                $lineNet = (int) round($unitNet * $quantity);
                $lineVat = 0;
                $lineTotal = $lineNet;
            }

            $net += $lineNet;
            $vat += $lineVat;
            $total += $lineTotal;
            $built[] = [
                'line_type' => $line['line_type'],
                'description' => $description,
                'quantity' => $quantity,
                'unit_net_pence' => $unitNet,
                'vat_rate_bps' => $rate,
                'vat_pence' => $lineVat,
                'line_total_pence' => $lineTotal,
            ];
        }

        if ($built === []) {
            throw ValidationException::withMessages(['lines' => 'Add at least one invoice line.']);
        }

        return ['lines' => $built, 'net' => $net, 'vat' => $vat, 'total' => $total, 'rate' => $rate];
    }
}
