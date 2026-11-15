<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class StudentInvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->isStudent(), 403);
        $invoices = Invoice::query()
            ->where('user_id', $request->user()->id)
            ->with(['course:id,title', 'batch:id,title', 'payments.receiver:id,name', 'refunds'])
            ->latest()->paginate(20);

        $invoices->getCollection()->transform(fn (Invoice $invoice) => $this->present($invoice));

        return response()->json([
            'data' => $invoices->items(),
            'meta' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
            ],
        ]);
    }

    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        abort_unless($request->user()->isStudent() && (int) $invoice->user_id === (int) $request->user()->id, 404);
        $invoice->load(['course:id,title', 'batch:id,title', 'payments.receiver:id,name', 'refunds']);

        return response()->json(['data' => $this->present($invoice)]);
    }

    public function printInvoice(Invoice $invoice)
    {
        $invoice->load(['user:id,name,email,phone_number', 'course:id,title', 'batch:id,title', 'payments.receiver:id,name', 'refunds']);

        return response()->view('student.invoices.print', [
            'invoice' => $invoice,
            'summary' => $this->totals($invoice),
        ])->header('Cache-Control', 'private, no-store, max-age=0')->header('X-Robots-Tag', 'noindex, nofollow')->header('Referrer-Policy', 'no-referrer');
    }

    public function printReceipt(Invoice $invoice, InvoicePayment $payment)
    {
        abort_unless((int) $payment->invoice_id === (int) $invoice->id, 404);
        $invoice->load(['user:id,name,email,phone_number', 'course:id,title', 'batch:id,title']);
        $payment->load('receiver:id,name');

        return response()->view('student.invoices.receipt-print', compact('invoice', 'payment'))
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer');
    }

    private function present(Invoice $invoice): array
    {
        $totals = $this->totals($invoice);

        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'title' => $invoice->title,
            'description' => $invoice->description,
            'status' => $invoice->status,
            'subtotal' => number_format((float) ($invoice->subtotal ?? $invoice->amount), 2, '.', ''),
            'discount_type' => $invoice->discount_type,
            'discount_value' => number_format((float) $invoice->discount_value, 2, '.', ''),
            'amount' => number_format((float) $invoice->amount, 2, '.', ''),
            'due_date' => $invoice->due_date?->toDateString(),
            'course' => $invoice->course ? ['id' => $invoice->course->id, 'title' => $invoice->course->title] : null,
            'batch' => $invoice->batch ? ['id' => $invoice->batch->id, 'title' => $invoice->batch->title] : null,
            ...$totals,
            'print_url' => URL::temporarySignedRoute('student.invoices.print', now()->addMinutes(10), ['invoice' => $invoice->id]),
            'payments' => $invoice->payments->map(fn (InvoicePayment $payment) => [
                'id' => $payment->id,
                'amount' => number_format((float) $payment->amount, 2, '.', ''),
                'method' => $payment->method,
                'reference' => $payment->reference,
                'note' => $payment->note,
                'paid_at' => $payment->paid_at?->toIso8601String(),
                'received_by' => $payment->receiver?->name,
                'print_url' => URL::temporarySignedRoute('student.invoices.receipts.print', now()->addMinutes(10), [
                    'invoice' => $invoice->id, 'payment' => $payment->id,
                ]),
            ])->values(),
        ];
    }

    private function totals(Invoice $invoice): array
    {
        $paid = (float) $invoice->payments->sum('amount');
        $refunded = (float) $invoice->refunds->where('status', 'approved')->sum('amount');
        $netPaid = max(0, $paid - $refunded);

        return [
            'gross_paid' => number_format($paid, 2, '.', ''),
            'refunded' => number_format($refunded, 2, '.', ''),
            'net_paid' => number_format($netPaid, 2, '.', ''),
            'outstanding' => number_format(max(0, (float) $invoice->amount - $netPaid), 2, '.', ''),
        ];
    }
}
