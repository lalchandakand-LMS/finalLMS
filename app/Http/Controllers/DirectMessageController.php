<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DirectMessageController extends Controller
{
    public function index(Request $request): View
    {
        $userId = $request->user()->id;

        $conversationUserIds = \App\Models\DirectMessage::query()
            ->where('sender_id', $userId)
            ->orWhere('recipient_id', $userId)
            ->get(['sender_id', 'recipient_id'])
            ->flatMap(fn ($message) => [$message->sender_id, $message->recipient_id])
            ->unique()
            ->reject(fn ($id) => (int) $id === (int) $userId)
            ->values();

        $users = User::query()
            ->whereIn('id', $conversationUserIds)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'profile_photo_path']);

        return view('community.messages.index', compact('users'));
    }

    public function show(Request $request, User $user): View
    {
        abort_if($user->id === $request->user()->id || $user->trashed(), 404);

        $messages = \App\Models\DirectMessage::query()
            ->where(function ($query) use ($request, $user) {
                $query->where('sender_id', $request->user()->id)->where('recipient_id', $user->id);
            })
            ->orWhere(function ($query) use ($request, $user) {
                $query->where('sender_id', $user->id)->where('recipient_id', $request->user()->id);
            })
            ->with('sender:id,name,role')
            ->latest('id')
            ->limit(100)
            ->get()
            ->reverse()
            ->values();

        \App\Models\DirectMessage::query()
            ->where('sender_id', $user->id)
            ->where('recipient_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return view('community.messages.show', compact('user', 'messages'));
    }

    public function store(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id || $user->trashed(), 404);

        $data = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:3000'],
        ]);

        \App\Models\DirectMessage::create([
            'sender_id' => $request->user()->id,
            'recipient_id' => $user->id,
            'body' => trim($data['body']),
        ]);

        return redirect()->route('community.messages.show', $user);
    }
}
