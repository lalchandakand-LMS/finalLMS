@extends('layouts.app')
@section('content')
<div class="mx-auto max-w-5xl space-y-6 p-4 sm:p-6">
<header><h1 class="text-2xl font-bold">Library reservations</h1><p class="text-sm text-gray-600">Reserve a book, follow your queue and collect an available copy within three days.</p></header>
@if(session('success'))<p class="rounded bg-green-50 p-3 text-green-800">{{ session('success') }}</p>@endif
@if(isset($errors) && $errors->any())<p class="rounded bg-red-50 p-3 text-red-800">{{ $errors->first() }}</p>@endif
<section class="rounded border bg-white p-5"><h2 class="mb-3 font-semibold">Catalogue</h2><div class="divide-y">
@forelse($books as $book)<div class="flex flex-wrap items-center justify-between gap-3 py-3"><div><p class="font-medium">{{ $book->title }}</p><p class="text-sm text-gray-500">{{ $book->author }} · {{ $book->category }} · {{ $book->available_copies }} available</p></div><form method="POST" action="{{ route(request()->routeIs('teacher.*')?'teacher.library.reservations.store':(request()->routeIs('staff.*')?'staff.library.reservations.store':'student.library.reservations.store')) }}">@csrf<input type="hidden" name="library_book_id" value="{{ $book->id }}"><button class="rounded bg-blue-700 px-4 py-2 text-white">Reserve</button></form></div>@empty<p class="py-4 text-gray-500">No books found.</p>@endforelse
</div>{{ $books->links() }}</section>
<section class="rounded border bg-white p-5"><h2 class="mb-3 font-semibold">My reservations</h2><div class="divide-y">
@forelse($reservations as $reservation)<div class="flex flex-wrap items-center justify-between gap-3 py-3"><div><p class="font-medium">{{ $reservation->book?->title ?? 'Book unavailable' }}</p><p class="text-sm text-gray-600">Status: {{ str_replace('_',' ',ucfirst($reservation->status)) }} @if($reservation->queue_position) · Queue #{{ $reservation->queue_position }} @endif @if($reservation->expires_at) · Collect by {{ $reservation->expires_at->format('d M Y, h:i A') }} @endif</p></div>@if(in_array($reservation->status,['waiting','ready_for_pickup'],true))<form method="POST" action="{{ route(request()->routeIs('teacher.*')?'teacher.library.reservations.cancel':(request()->routeIs('staff.*')?'staff.library.reservations.cancel':'student.library.reservations.cancel'),$reservation->id) }}">@csrf<button class="rounded border px-3 py-2">Cancel</button></form>@endif</div>@empty<p class="py-4 text-gray-500">You have no reservations.</p>@endforelse
</div></section></div>
@endsection
