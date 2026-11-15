<?php

namespace App\\Http\\Controllers;

use App\\Models\\SupportTicket;
use App\\Models\\SupportTicketMessage;
use Illuminate\\Http\\RedirectResponse;
use Illuminate\\Http\\Request;
use Illuminate\\View\\View;
use Illuminate\\Support\\Facades\\DB;

class AdminSupportTicketController extends Controller
{
    public function index(Request $request): View
    {
        $tickets = SupportTicket::query()
            ->with(['user:id,name,email', 'branch:id,name', 'latestMessage'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest('last_message_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.support.index', compact('tickets'));
    }

    public function show(SupportTicket $ticket): View
    {
        $ticket->messages()
            ->where('sender_role', 'student')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
        $ticket->load(['user:id,name,email', 'branch:id,name', 'messages.sender:id,name,role']);

        return view('admin.support.show', compact('ticket'));
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'min:2', 'max:5000']]);

        DB::transaction(function () use ($ticket, $request, $data): void {
            $locked = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id);
            abort_if($locked->status === 'closed', 409, __('This support ticket is closed.'));
            $locked->messages()->create([
                'sender_id' => $request->user()->id,
                'sender_role' => 'admin',
                'body' => trim($data['message']),
            ]);
            $locked->update(['status' => 'in_progress', 'last_message_at' => now()]);
        });

        return back()->with('success', __('Your reply has been sent.'));
    }

    public function updateStatus(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:open,in_progress,closed']]);
        $ticket->update([
            'status' => $data['status'],
            'closed_at' => $data['status'] === 'closed' ? now() : null,
        ]);

        return back()->with('success', __('Support ticket status updated.'));
    }
}
