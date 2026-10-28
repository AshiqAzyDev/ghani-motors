<?php

namespace App\Services\Mail;

use App\Mail\BrandedMail;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Dealer;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Lead;
use App\Models\Sale;
use App\Models\StaffUser;
use App\Models\Vehicle;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class GhaniMailer
{
    public function welcome(Customer $customer): void
    {
        $dealer = $customer->dealer ?? Dealer::current();
        $this->toCustomer($customer, 'Welcome to Ghani Motors', 'emails.customer.welcome', [
            'heading' => 'Welcome, '.$customer->name.'.',
            'eyebrow' => 'Customer account',
            'name' => $customer->name,
            'dealer' => $dealer,
            'actionUrl' => route('account'),
            'actionLabel' => 'Open your account',
            'secondaryUrl' => route('stock'),
            'secondaryLabel' => 'Browse cars in stock',
        ]);
    }

    public function passwordReset(Customer $customer, string $url): void
    {
        $this->toCustomer($customer, 'Reset your Ghani Motors password', 'emails.customer.password-reset', [
            'heading' => 'Reset your password',
            'eyebrow' => 'Account security',
            'name' => $customer->name,
            'dealer' => $customer->dealer,
            'actionUrl' => $url,
            'actionLabel' => 'Choose a new password',
        ]);
    }

    public function staffPasswordReset(StaffUser $staff, string $url): void
    {
        $this->send($staff->email, 'Reset your dealer workspace password', 'emails.staff.password-reset', [
            'heading' => 'Reset your staff password',
            'eyebrow' => 'Dealer workspace',
            'name' => $staff->name,
            'dealer' => $staff->dealer,
            'actionUrl' => $url,
            'actionLabel' => 'Choose a new password',
        ]);
    }

    public function staffInvite(StaffUser $staff, string $url): void
    {
        $this->send($staff->email, 'Your Ghani Motors dealer workspace access', 'emails.staff.invite', [
            'heading' => 'You have been invited to the dealer workspace',
            'eyebrow' => 'Staff access',
            'name' => $staff->name,
            'role' => $staff->role,
            'dealer' => $staff->dealer,
            'actionUrl' => $url,
            'actionLabel' => 'Set your password',
            'secondaryUrl' => route('dealer.login'),
            'secondaryLabel' => 'Go to dealer login',
        ]);
    }

    public function enquiryCustomer(Customer $customer, Lead $lead, ?Vehicle $vehicle = null): void
    {
        $dealer = $customer->dealer ?? Dealer::current();
        $this->toCustomer($customer, 'We have your enquiry', 'emails.customer.enquiry-received', [
            'heading' => 'Thanks for getting in touch',
            'eyebrow' => 'Enquiry received',
            'name' => $customer->name,
            'lead' => $lead,
            'vehicle' => $vehicle,
            'dealer' => $dealer,
            'showroomAddress' => $dealer->showroomAddress(),
            'mapUrl' => $dealer->mapsSearchUrl(),
            'directionsUrl' => $dealer->mapsDirectionsUrl(),
            'actionUrl' => $vehicle?->slug ? route('cars.show', $vehicle->slug) : route('stock'),
            'actionLabel' => $vehicle ? 'View this car' : 'Browse stock',
            'secondaryUrl' => $dealer->mapsDirectionsUrl(),
            'secondaryLabel' => 'Get directions to the showroom',
        ]);
    }

    public function enquiryDealer(Dealer $dealer, Customer $customer, Lead $lead, ?Vehicle $vehicle = null): void
    {
        $this->toDealerStaff($dealer, 'New website enquiry'.($vehicle ? ': '.$vehicle->title() : ''), 'emails.dealer.enquiry-alert', [
            'heading' => 'New enquiry from '.$customer->name,
            'eyebrow' => 'Dealer alert',
            'customer' => $customer,
            'lead' => $lead,
            'vehicle' => $vehicle,
            'dealer' => $dealer,
            'actionUrl' => route('dealer.leads'),
            'actionLabel' => 'Open leads',
        ]);
    }

    public function reservationCustomer(Customer $customer, Booking $booking, Vehicle $vehicle): void
    {
        $this->toCustomer($customer, 'Reservation request received', 'emails.customer.reservation-received', [
            'heading' => 'Your reservation request is in',
            'eyebrow' => 'Reservation',
            'name' => $customer->name,
            'booking' => $booking,
            'vehicle' => $vehicle,
            'dealer' => $customer->dealer,
            'actionUrl' => route('cars.show', $vehicle->slug),
            'actionLabel' => 'View the car',
        ]);
    }

    public function reservationDealer(Dealer $dealer, Customer $customer, Booking $booking, Vehicle $vehicle): void
    {
        $this->toDealerStaff($dealer, 'Reservation held: '.$vehicle->title(), 'emails.dealer.reservation-alert', [
            'heading' => 'Reservation held for '.$customer->name,
            'eyebrow' => 'Dealer alert',
            'customer' => $customer,
            'booking' => $booking,
            'vehicle' => $vehicle,
            'dealer' => $dealer,
            'actionUrl' => route('dealer.bookings'),
            'actionLabel' => 'Open bookings',
        ]);
    }

    public function appointmentCustomer(Customer $customer, Booking $booking, Vehicle $vehicle): void
    {
        $label = $booking->type === 'viewing' ? 'viewing' : 'test drive';
        $this->toCustomer($customer, 'Your '.$label.' request', 'emails.customer.appointment-received', [
            'heading' => 'We have your '.$label.' request',
            'eyebrow' => ucfirst($label),
            'name' => $customer->name,
            'booking' => $booking,
            'vehicle' => $vehicle,
            'label' => $label,
            'dealer' => $customer->dealer,
            'actionUrl' => route('cars.show', $vehicle->slug),
            'actionLabel' => 'View the car',
        ]);
    }

    public function appointmentDealer(Dealer $dealer, Customer $customer, Booking $booking, Vehicle $vehicle): void
    {
        $label = $booking->type === 'viewing' ? 'Viewing' : 'Test drive';
        $this->toDealerStaff($dealer, $label.' request: '.$vehicle->title(), 'emails.dealer.appointment-alert', [
            'heading' => $label.' request from '.$customer->name,
            'eyebrow' => 'Dealer alert',
            'customer' => $customer,
            'booking' => $booking,
            'vehicle' => $vehicle,
            'label' => strtolower($label),
            'dealer' => $dealer,
            'actionUrl' => route('dealer.bookings'),
            'actionLabel' => 'Open bookings',
        ]);
    }

    public function valuationCustomer(Customer $customer, Lead $lead): void
    {
        $this->toCustomer($customer, 'We have your car details', 'emails.customer.valuation-received', [
            'heading' => 'Thanks for telling us about your car',
            'eyebrow' => 'Sell your car',
            'name' => $customer->name,
            'lead' => $lead,
            'dealer' => $customer->dealer,
            'actionUrl' => route('sell'),
            'actionLabel' => 'Back to sell your car',
        ]);
    }

    public function valuationDealer(Dealer $dealer, Customer $customer, Lead $lead): void
    {
        $this->toDealerStaff($dealer, 'New sell / valuation enquiry', 'emails.dealer.valuation-alert', [
            'heading' => 'New valuation enquiry from '.$customer->name,
            'eyebrow' => 'Dealer alert',
            'customer' => $customer,
            'lead' => $lead,
            'dealer' => $dealer,
            'actionUrl' => route('dealer.leads'),
            'actionLabel' => 'Open leads',
        ]);
    }

    public function financeCustomer(Customer $customer, Lead $lead, ?Vehicle $vehicle = null): void
    {
        $this->toCustomer($customer, 'Finance enquiry received', 'emails.customer.finance-received', [
            'heading' => 'Thanks for your finance enquiry',
            'eyebrow' => 'Finance',
            'name' => $customer->name,
            'lead' => $lead,
            'vehicle' => $vehicle,
            'dealer' => $customer->dealer,
            'actionUrl' => route('finance'),
            'actionLabel' => 'Back to finance',
        ]);
    }

    public function financeDealer(Dealer $dealer, Customer $customer, Lead $lead, ?Vehicle $vehicle = null): void
    {
        $this->toDealerStaff($dealer, 'Finance enquiry'.($vehicle ? ': '.$vehicle->title() : ''), 'emails.dealer.finance-alert', [
            'heading' => 'Finance enquiry from '.$customer->name,
            'eyebrow' => 'Dealer alert',
            'customer' => $customer,
            'lead' => $lead,
            'vehicle' => $vehicle,
            'dealer' => $dealer,
            'actionUrl' => route('dealer.leads'),
            'actionLabel' => 'Open leads',
        ]);
    }

    public function bookingUpdated(Customer $customer, Booking $booking, Vehicle $vehicle): void
    {
        $status = $booking->status;
        $subject = match ($status) {
            'confirmed' => 'Your booking is confirmed',
            'cancelled' => 'Your booking has been cancelled',
            'completed' => 'Your booking is complete',
            default => 'Booking update from Ghani Motors',
        };

        $this->toCustomer($customer, $subject, 'emails.customer.booking-updated', [
            'heading' => $subject,
            'eyebrow' => 'Booking '.$status,
            'name' => $customer->name,
            'booking' => $booking,
            'vehicle' => $vehicle,
            'dealer' => $customer->dealer,
            'actionUrl' => route('cars.show', $vehicle->slug),
            'actionLabel' => 'View the car',
        ]);
    }

    public function leadContacted(Customer $customer, Lead $lead): void
    {
        $this->toCustomer($customer, 'Ghani Motors will be in touch', 'emails.customer.lead-contacted', [
            'heading' => 'Our team has picked up your enquiry',
            'eyebrow' => 'Follow-up',
            'name' => $customer->name,
            'lead' => $lead,
            'dealer' => $customer->dealer,
            'actionUrl' => route('stock'),
            'actionLabel' => 'Browse stock',
        ]);
    }

    public function invoiceIssued(Customer $customer, Invoice $invoice): void
    {
        $kind = $invoice->kind === 'credit_note' ? 'Credit note' : 'Invoice';
        $this->toCustomer($customer, $kind.' '.$invoice->number.' from Ghani Motors', 'emails.customer.invoice-issued', [
            'heading' => $kind.' '.$invoice->number,
            'eyebrow' => $kind,
            'name' => $customer->name,
            'invoice' => $invoice,
            'total' => Money::gbp($invoice->total_pence),
            'dealer' => $customer->dealer,
            'actionUrl' => route('account'),
            'actionLabel' => 'Open your account',
        ]);
    }

    public function paymentReceived(Customer $customer, Invoice $invoice, InvoicePayment $payment): void
    {
        $this->toCustomer($customer, 'Payment received for '.$invoice->number, 'emails.customer.payment-received', [
            'heading' => 'Payment received',
            'eyebrow' => 'Invoice '.$invoice->number,
            'name' => $customer->name,
            'invoice' => $invoice,
            'payment' => $payment,
            'amount' => Money::gbp($payment->amount_pence),
            'balance' => Money::gbp($invoice->fresh()->balancePence()),
            'dealer' => $customer->dealer,
            'actionUrl' => route('account'),
            'actionLabel' => 'Open your account',
        ]);
    }

    public function invoiceVoided(Customer $customer, Invoice $invoice): void
    {
        $this->toCustomer($customer, 'Invoice '.$invoice->number.' has been voided', 'emails.customer.invoice-voided', [
            'heading' => 'Invoice '.$invoice->number.' voided',
            'eyebrow' => 'Invoice update',
            'name' => $customer->name,
            'invoice' => $invoice,
            'dealer' => $customer->dealer,
            'actionUrl' => route('account'),
            'actionLabel' => 'Open your account',
        ]);
    }

    public function saleCompleted(Customer $customer, Sale $sale, Vehicle $vehicle, Dealer $dealer): void
    {
        $this->toCustomer($customer, 'Sale complete: '.$vehicle->title(), 'emails.customer.sale-completed', [
            'heading' => 'Congratulations on your next car',
            'eyebrow' => 'Sale complete',
            'name' => $customer->name,
            'sale' => $sale,
            'vehicle' => $vehicle,
            'price' => Money::gbp($sale->price_pence),
            'dealer' => $dealer,
            'actionUrl' => route('account'),
            'actionLabel' => 'Open your account',
        ]);

        $this->toDealerStaff($dealer, 'Sale completed: '.$vehicle->title(), 'emails.dealer.sale-completed', [
            'heading' => 'Sale completed for '.$customer->name,
            'eyebrow' => 'Dealer alert',
            'customer' => $customer,
            'sale' => $sale,
            'vehicle' => $vehicle,
            'price' => Money::gbp($sale->price_pence),
            'dealer' => $dealer,
            'actionUrl' => route('dealer.sales'),
            'actionLabel' => 'Open sales',
        ]);
    }

    public function carUnavailable(Customer $customer, Vehicle $vehicle, string $reason = 'This car is no longer available.'): void
    {
        $this->toCustomer($customer, 'Update on '.$vehicle->title(), 'emails.customer.car-unavailable', [
            'heading' => 'An update on a car you were interested in',
            'eyebrow' => 'Stock update',
            'name' => $customer->name,
            'vehicle' => $vehicle,
            'reason' => $reason,
            'dealer' => $customer->dealer,
            'actionUrl' => route('stock'),
            'actionLabel' => 'See cars in stock',
        ]);
    }

    public function reservationReminder(Customer $customer, Booking $booking, Vehicle $vehicle): void
    {
        $this->toCustomer($customer, 'Reminder: reservation for '.$vehicle->title(), 'emails.customer.reservation-reminder', [
            'heading' => 'Reminder about your reserved car',
            'eyebrow' => 'Reservation reminder',
            'name' => $customer->name,
            'booking' => $booking,
            'vehicle' => $vehicle,
            'dealer' => $customer->dealer,
            'actionUrl' => route('cars.show', $vehicle->slug),
            'actionLabel' => 'View the car',
        ]);
    }

    public function appointmentReminder(Customer $customer, Booking $booking, Vehicle $vehicle): void
    {
        $label = $booking->type === 'viewing' ? 'viewing' : 'test drive';
        $this->toCustomer($customer, 'Reminder: your '.$label.' tomorrow', 'emails.customer.appointment-reminder', [
            'heading' => 'Reminder: your '.$label.' is coming up',
            'eyebrow' => ucfirst($label).' reminder',
            'name' => $customer->name,
            'booking' => $booking,
            'vehicle' => $vehicle,
            'label' => $label,
            'dealer' => $customer->dealer,
            'actionUrl' => route('cars.show', $vehicle->slug),
            'actionLabel' => 'View the car',
        ]);
    }

    public function leadDigest(Dealer $dealer, Collection $leads): void
    {
        if ($leads->isEmpty()) {
            return;
        }

        $this->toDealerStaff($dealer, 'Lead digest: '.$leads->count().' open enquiries', 'emails.dealer.lead-digest', [
            'heading' => 'Open enquiries needing attention',
            'eyebrow' => 'Daily digest',
            'leads' => $leads,
            'count' => $leads->count(),
            'dealer' => $dealer,
            'actionUrl' => route('dealer.leads', ['status' => 'new']),
            'actionLabel' => 'Open new leads',
        ]);
    }

    public function syncFailure(Dealer $dealer, string $title, string $detail): void
    {
        $this->toDealerAdmins($dealer, $title, 'emails.dealer.sync-failure', [
            'heading' => $title,
            'eyebrow' => 'System alert',
            'detail' => $detail,
            'dealer' => $dealer,
            'actionUrl' => route('dealer.overview'),
            'actionLabel' => 'Open overview',
        ]);
    }

    private function toCustomer(Customer $customer, string $subject, string $view, array $payload): void
    {
        if (! filter_var($customer->email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $this->send($customer->email, $subject, $view, $payload);
    }

    private function toDealerStaff(Dealer $dealer, string $subject, string $view, array $payload): void
    {
        foreach ($this->staffEmails($dealer, ['admin', 'sales']) as $email) {
            $this->send($email, $subject, $view, $payload);
        }
    }

    private function toDealerAdmins(Dealer $dealer, string $subject, string $view, array $payload): void
    {
        foreach ($this->staffEmails($dealer, ['admin']) as $email) {
            $this->send($email, $subject, $view, $payload);
        }
    }

    private function staffEmails(Dealer $dealer, array $roles): array
    {
        return StaffUser::query()
            ->where('dealer_id', $dealer->id)
            ->where('is_active', true)
            ->whereIn('role', $roles)
            ->pluck('email')
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }

    private function send(string $email, string $subject, string $view, array $payload): void
    {
        try {
            Mail::to($email)->send(new BrandedMail($subject, $view, $payload));
        } catch (Throwable $exception) {
            Log::warning('Ghani mail failed', [
                'to' => $email,
                'subject' => $subject,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
