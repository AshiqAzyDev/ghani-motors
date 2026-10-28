<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Sale;
use App\Services\InvoiceService;
use App\Support\Money;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(private InvoiceService $invoices) {}

    public function index(Request $request)
    {
        $dealerId = $request->user('staff')->dealer_id;
        $rows = Invoice::query()
            ->whereHas('customer', fn ($q) => $q->where('dealer_id', $dealerId))
            ->with('customer')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(function ($q) use ($term) {
                    $q->where('number', 'like', $term)
                        ->orWhere('vehicle_label', 'like', $term)
                        ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $term)->orWhere('email', 'like', $term));
                });
            })
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('dealer.invoices.index', compact('rows'));
    }

    public function create(Request $request)
    {
        $dealerId = $request->user('staff')->dealer_id;

        return view('dealer.invoices.form', [
            'customers' => Customer::query()->where('dealer_id', $dealerId)->orderBy('name')->get(),
            'sales' => Sale::query()->where('status', 'completed')->whereHas('vehicle', fn ($q) => $q->where('dealer_id', $dealerId))->whereDoesntHave('invoice')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $staff = $request->user('staff');
        $data = $request->validate([
            'customer_id' => ['required', 'integer'],
            'sale_id' => ['nullable', 'integer'],
            'vehicle_label' => ['nullable', 'string', 'max:180'],
            'vat_scheme' => ['required', 'in:standard,margin,exempt,zero_rated'],
            'prices_include_vat' => ['nullable', 'boolean'],
            'lines' => ['required', 'array'],
            'lines.*.line_type' => ['nullable', 'in:vehicle,extra,part_exchange,fee,discount'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'lines.*.amount' => ['nullable', 'numeric'],
        ]);
        $data['prices_include_vat'] = $request->boolean('prices_include_vat');
        $invoice = $this->invoices->createInvoice($staff->dealer, $data, $data['lines'], $staff);

        return redirect()->route('dealer.invoices.show', $invoice)->with('status', 'Draft invoice '.$invoice->number.' created.');
    }

    public function show(Request $request, Invoice $invoice)
    {
        $this->owns($request, $invoice);
        $invoice->load(['lines', 'payments', 'customer', 'related', 'creator']);

        return view('dealer.invoices.show', [
            'invoice' => $invoice,
            'dealer' => $request->user('staff')->dealer,
        ]);
    }

    public function print(Request $request, Invoice $invoice)
    {
        $this->owns($request, $invoice);
        $invoice->load(['lines', 'payments', 'creator']);

        return view('dealer.invoices.print', [
            'invoice' => $invoice,
            'dealer' => $request->user('staff')->dealer,
        ]);
    }

    public function issue(Request $request, Invoice $invoice)
    {
        $this->owns($request, $invoice);
        $this->invoices->issue($invoice, $request->user('staff'));

        return back()->with('status', 'Invoice issued.');
    }

    public function pay(Request $request, Invoice $invoice)
    {
        $this->owns($request, $invoice);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'received_on' => ['required', 'date'],
            'method' => ['required', 'in:bank_transfer,card,finance,cash'],
            'reference' => ['required', 'string', 'max:100'],
        ]);
        $this->invoices->addPayment($invoice, Money::poundsToPence($data['amount']), $data['received_on'], $data['method'], $data['reference'], $request->user('staff'));

        return back()->with('status', 'Payment recorded.');
    }

    public function void(Request $request, Invoice $invoice)
    {
        $this->owns($request, $invoice);
        $this->invoices->void($invoice, $request->user('staff'));

        return back()->with('status', 'Invoice voided.');
    }

    public function credit(Request $request, Invoice $invoice)
    {
        $this->owns($request, $invoice);
        $credit = $this->invoices->credit($request->user('staff')->dealer, $invoice, $request->user('staff'));

        return redirect()->route('dealer.invoices.show', $credit)->with('status', 'Credit note issued.');
    }

    private function owns(Request $request, Invoice $invoice): void
    {
        abort_unless((int) $invoice->customer->dealer_id === (int) $request->user('staff')->dealer_id, 404);
    }
}
