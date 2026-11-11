<?php

namespace App\Http\Controllers;

use App\Models\DiscussionMessage;
use App\Models\DiscussionRoom;
use App\Models\DiscussionRoomMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DiscussionRoomController extends Controller
{
    public function index(): View
    {
        $rooms = DiscussionRoom::query()
            ->where('is_active', true)
            ->with('owner:id,name,role')
            ->withCount('members')
            ->latest()
            ->paginate(20);

        return view('community.rooms.index', compact('rooms'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(in_array($request->user()->role, ['admin', 'teacher'], true), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'description' => ['nullable', 'string', 'max:1500'],
            'visibility' => ['required', 'in:public,private'],
        ]);

        $room = DiscussionRoom::create([
            'created_by' => $request->user()->id,
            'name' => trim($data['name']),
            'description' => $data['description'] ?? null,
            'visibility' => $data['visibility'],
            'is_active' => true,
        ]);

        DiscussionRoomMember::create([
            'room_id' => $room->id,
            'user_id' => $request->user()->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        return redirect()->route('community.rooms.show', $room)->with('success', 'Discussion room created.');
    }

    public function join(Request $request, DiscussionRoom $room): RedirectResponse
    {
        abort_unless($room->is_active, 404);
        abort_if($room->visibility === 'private' && $room->created_by !== $request->user()->id, 403);

        DiscussionRoomMember::query()->firstOrCreate(
            ['room_id' => $room->id, 'user_id' => $request->user()->id],
            ['role' => 'member', 'joined_at' => now()]
        );

        return redirect()->route('community.rooms.show', $room);
    }

    public function show(Request $request, DiscussionRoom $room): View
    {
        abort_unless($room->is_active, 404);
        $isMember = $room->members()->where('user_id', $request->user()->id)->exists();
        abort_unless($isMember, 403);

        $messages = $room->messages()
            ->with('user:id,name,role,profile_photo_path')
            ->latest('id')
            ->limit(50)
            ->get()
            ->reverse()
            ->values();

        return view('community.rooms.show', compact('room', 'messages'));
    }

    public function message(Request $request, DiscussionRoom $room): RedirectResponse
    {
        abort_unless($room->is_active, 404);
        abort_unless($room->members()->where('user_id', $request->user()->id)->exists(), 403);

        $data = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:3000'],
        ]);

        $room->messages()->create([
            'user_id' => $request->user()->id,
            'body' => trim($data['body']),
        ]);

        return back();
    }
}
