<?php

namespace App\Http\Controllers;

use App\Models\PaymentAuditEvent;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentAuditController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'payment_id' => ['nullable', 'integer'],
            'invoice_refund_id' => ['nullable', 'integer'],
            'invoice_payment_id' => ['nullable', 'integer'],
            'ebook_refund_id' => ['nullable', 'integer'],
            'event' => ['nullable', 'string', 'max:80'],
        ]);

        $query = PaymentAuditEvent::with(['actor', 'payment.user', 'invoiceRefund', 'ebookRefund'])
            ->latest();
        if (! empty($validated['payment_id'])) {
            $query->where('payment_id', $validated['payment_id']);
        }
        if (! empty($validated['invoice_payment_id'])) {
            $query->where('invoice_payment_id', $validated['invoice_payment_id']);
        }
        if (! empty($validated['ebook_refund_id'])) {
            $query->where('ebook_refund_id', $validated['ebook_refund_id']);
        }
        if (! empty($validated['invoice_refund_id'])) {
            $query->where('invoice_refund_id', $validated['invoice_refund_id']);
        }
        if (! empty($validated['event'])) {
            $query->where('event', $validated['event']);
        }

        return view('admin.payment-audit.index', [
            'events' => $query->paginate(50)->withQueryString(),
            'filters' => $validated,
        ]);
    }
}
