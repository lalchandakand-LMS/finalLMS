<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentSupportTicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $student = $request->user();
        abort_unless($student->isStudent(), 403);

        $tickets = SupportTicket::query()
            ->where('user_id', $student->id)
            ->with('latestMessage')
            ->latest('last_message_at')
            ->paginate(15);

        return response()->json([
            'data' => $tickets->getCollection()->map(fn (SupportTicket $ticket): array => $this->ticketData($ticket)),
            'meta' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $student = $request->user();
        abort_unless($student->isStudent(), 403);

        $data = $request->validate([
            'subject' => ['required', 'string', 'min:5', 'max:160'],
            'message' => ['required', 'string', 'min:5', 'max:5000'],
        ]);

        $ticket = DB::transaction(function () use ($student, $data): SupportTicket {
            $ticket = SupportTicket::create([
                'user_id' => $student->id,
                'branch_id' => $student->branch_id,
                'subject' => trim($data['subject']),
                'status' => 'open',
                'last_message_at' => now(),
            ]);
            $ticket->messages()->create([
                'sender_id' => $student->id,
                'sender_role' => 'student',
                'body' => trim($data['message']),
            ]);

            return $ticket;
        });

        return response()->json([
            'message' => 'Your support request has been submitted.',
            'data' => $this->ticketData($ticket->load('latestMessage')),
        ], 201);
    }

    public function show(Request $request, SupportTicket $ticket): JsonResponse
    {
        $student = $request->user();
        abort_unless($student->isStudent(), 403);
        abort_unless((int) $ticket->user_id === (int) $student->id, 404);

        $ticket->messages()
            ->where('sender_role', 'admin')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $ticket->load(['messages.sender:id,name,role']);

        return response()->json([
            'data' => [
                ...$this->ticketData($ticket),
                'messages' => $ticket->messages->map(fn (SupportTicketMessage $message): array => [
                    'id' => $message->id,
                    'sender_name' => $message->sender?->name ?? 'Academy support',
                    'sender_role' => $message->sender_role,
                    'body' => $message->body,
                    'created_at' => $message->created_at?->toIso8601String(),
                ])->values(),
            ],
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket): JsonResponse
    {
        $student = $request->user();
        abort_unless($student->isStudent(), 403);
        abort_unless((int) $ticket->user_id === (int) $student->id, 404);

        $data = $request->validate(['message' => ['required', 'string', 'min:2', 'max:5000']]);
        if ($ticket->status === 'closed') {
            return response()->json(['message' => 'This support ticket is closed. Create a new ticket for further help.'], 409);
        }

        $message = DB::transaction(function () use ($ticket, $student, $data): SupportTicketMessage {
            $locked = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id);
            abort_if($locked->status === 'closed', 409, 'This support ticket is closed.');

            $message = $locked->messages()->create([
                'sender_id' => $student->id,
                'sender_role' => 'student',
                'body' => trim($data['message']),
            ]);
            $locked->update(['last_message_at' => now()]);

            return $message;
        });

        return response()->json([
            'message' => 'Your reply has been sent.',
            'data' => [
                'id' => $message->id,
                'sender_name' => $student->name,
                'sender_role' => 'student',
                'body' => $message->body,
                'created_at' => $message->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    private function ticketData(SupportTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'subject' => $ticket->subject,
            'status' => $ticket->status,
            'branch' => $ticket->branch?->name,
            'last_message' => $ticket->latestMessage?->body,
            'last_message_at' => $ticket->last_message_at?->toIso8601String(),
            'created_at' => $ticket->created_at?->toIso8601String(),
        ];
    }
}
