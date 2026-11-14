<?php

namespace App\Http\Controllers\Payments;

use App\Exceptions\ChapaException;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Sale;
use App\Services\PaymentService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    private const STATUSES = [
        Payment::STATUS_PENDING, Payment::STATUS_SUCCESS, Payment::STATUS_FAILED,
        Payment::STATUS_CANCELLED, Payment::STATUS_EXPIRED,
    ];

    // The service owns the rules. This controller only translates between the web and the service.
    public function __construct(private PaymentService $payments)
    {
    }

    // PAY-15: the list, newest first, with filters.
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'provider' => ['nullable', Rule::in([Payment::PROVIDER_CHAPA])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        // Dates are business days in Addis Ababa, but the database stores UTC.
        $timezone = 'Africa/Addis_Ababa';
        $from = isset($filters['from']) ? Carbon::parse($filters['from'], $timezone)->startOfDay()->utc() : null;
        $to = isset($filters['to']) ? Carbon::parse($filters['to'], $timezone)->endOfDay()->utc() : null;

        $payments = Payment::query()
            ->with('sale:id,sale_number')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['provider'] ?? null, fn ($query, $provider) => $query->where('provider', $provider))
            ->when($from, fn ($query) => $query->where('created_at', '>=', $from))
            ->when($to, fn ($query) => $query->where('created_at', '<=', $to))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Payment $payment) => [
                'id' => $payment->id,
                'tx_ref' => $payment->tx_ref,
                'sale_id' => $payment->sale_id,
                'sale_number' => $payment->sale?->sale_number,
                'amount' => (string) $payment->amount,      // an exact string like "25.00"
                'currency' => $payment->currency,
                'status' => $payment->status,
                'created_at' => $payment->created_at?->toIso8601String(),
                'paid_at' => $payment->paid_at?->toIso8601String(),
            ]);

        return Inertia::render('Payments/Index', [
            'payments' => $payments,
            'filters' => $filters,
            'statuses' => self::STATUSES,
            'providers' => [Payment::PROVIDER_CHAPA],
        ]);
    }

    // PAY-15: one payment with its event timeline.
    public function show(Request $request, Payment $payment): Response
    {
        $payment->load(['sale.customer', 'events']);

        // The raw payloads hold customer details, so they need their own permission, checked HERE on the server.
        $canViewPayload = $request->user()->hasPermission('payments.view_payload');

        // PAY-10: why was this payment flagged? (The audit rows written by PaymentService.)
        $flags = DB::table('audit_logs')
            ->where('action', 'payment.flagged_for_review')
            ->where('entity_type', 'payment')
            ->where('entity_id', $payment->id)
            ->orderBy('id')
            ->get()
            ->map(function ($row) {
                $values = json_decode((string) $row->new_values, true) ?: [];

                return [
                    'at' => Carbon::parse($row->created_at, 'UTC')->toIso8601String(),
                    'reasons' => $values['reasons'] ?? [],
                    'expected' => $values['expected'] ?? null,
                    'received' => $values['received'] ?? null,
                ];
            })->all();

        return Inertia::render('Payments/Show', [
            'payment' => [
                'id' => $payment->id,
                'tx_ref' => $payment->tx_ref,
                'provider' => $payment->provider,
                'provider_transaction_id' => $payment->provider_transaction_id,
                'status' => $payment->status,
                'amount' => (string) $payment->amount,
                'currency' => $payment->currency,
                // The link is only useful (and only shown) while the payment can still be paid.
                'checkout_url' => $payment->status === Payment::STATUS_PENDING ? $payment->checkout_url : null,
                'created_at' => $payment->created_at?->toIso8601String(),
                'verified_at' => $payment->verified_at?->toIso8601String(),
                'paid_at' => $payment->paid_at?->toIso8601String(),
                'sale' => [
                    'id' => $payment->sale->id,
                    'sale_number' => $payment->sale->sale_number,
                    'status' => $payment->sale->status,
                    'customer' => $payment->sale->customer?->name,
                ],
            ],
            'events' => $payment->events->map(fn ($event) => [
                'id' => $event->id,
                'event_type' => $event->event_type,
                'received_at' => $event->received_at?->toIso8601String(),
                'processed_at' => $event->processed_at?->toIso8601String(),
                'payload' => $canViewPayload ? $event->payload : null,
            ])->all(),
            'flags' => $flags,
            'canViewPayload' => $canViewPayload,
        ]);
    }

    // PAY-01: starts a payment for a pending sale. The sale page then shows the link and the QR code.
    public function store(Sale $sale): RedirectResponse
    {
        try {
            $this->payments->start($sale);
        } catch (ChapaException|DomainException $e) {
            // For example "Chapa is not configured ..." or "Only a pending sale can be paid".
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return redirect()->route('sales.show', $sale)->with('success', 'Payment link created. Show it to the customer.');
    }

    // PAY-16: asks Chapa again. Safe to repeat: a settled payment is never changed twice.
    public function verify(Payment $payment): RedirectResponse
    {
        try {
            $result = $this->payments->verify($payment);
        } catch (ChapaException $e) {
            return redirect()->route('payments.show', $payment)->withErrors(['verify' => $e->getMessage()]);
        }

        return redirect()->route('payments.show', $payment)->with('success', "Checked with Chapa: the payment is {$result->status}.");
    }
}