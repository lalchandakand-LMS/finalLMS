@extends('layouts.app')

@section('title', __('Guardian Dashboard').' | '.config('app.name'))

@section('content')
    <div class="page-header">
        <div>
            <span class="badge info">{{ __('Guardian Portal') }}</span>
            <h1 style="margin:10px 0 0;">{{ __('Guardian Dashboard') }}</h1>
            <p class="muted">{{ __('View learning progress, attendance, results and fees only for children who have approved your access.') }}</p>
        </div>
        <span class="badge success">{{ __('Verified guardian account') }}</span>
    </div>

    @if(session('status'))
        <div class="flash success">{{ session('status') }}</div>
    @endif

    <div class="panel" style="margin-bottom:18px;">
        <h2 style="margin-top:0;">{{ __('Request access to a student') }}</h2>
        <p class="muted">{{ __('Enter the student account ID. The student must approve the request from their own account before any information is shared.') }}</p>
        <form method="POST" action="{{ route('guardian.link-requests.store') }}" class="grid grid-3">
            @csrf
            <div>
                <label for="student_id">{{ __('Student account ID') }}</label>
                <input id="student_id" name="student_id" type="number" min="1" required value="{{ old('student_id') }}">
                @error('student_id')<small class="error">{{ $message }}</small>@enderror
            </div>
            <div>
                <label for="relationship">{{ __('Relationship') }}</label>
                <select id="relationship" name="relationship" required>
                    <option value="father" @selected(old('relationship') === 'father')>{{ __('Father') }}</option>
                    <option value="mother" @selected(old('relationship') === 'mother')>{{ __('Mother') }}</option>
                    <option value="legal_guardian" @selected(old('relationship') === 'legal_guardian')>{{ __('Legal guardian') }}</option>
                    <option value="other" @selected(old('relationship') === 'other')>{{ __('Other') }}</option>
                </select>
                @error('relationship')<small class="error">{{ $message }}</small>@enderror
            </div>
            <div style="display:flex;align-items:end;">
                <button type="submit" class="btn primary">{{ __('Send student approval request') }}</button>
            </div>
        </form>
        @if($pendingRequests->isNotEmpty())
            <div style="margin-top:14px;">
                <strong>{{ __('Pending requests') }}</strong>
                @foreach($pendingRequests as $requestLink)
                    <div class="course-item" style="margin-top:8px;">
                        {{ $requestLink->student?->name ?? __('Student account') }}
                        <span class="badge warning">{{ __('Awaiting student approval') }}</span>
                        <small class="muted">{{ __('Requested') }}: {{ $requestLink->requested_at?->format('d M Y H:i') }}</small>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    @if($children->isEmpty())
        <div class="panel">
            <h2>{{ __('No verified children linked yet') }}</h2>
            <p class="muted">{{ __('When a student approves your request, their permitted learning information will appear here. Pending requests do not provide access.') }}</p>
        </div>
    @else
        <div class="panel" style="margin-bottom:18px;">
            <div class="page-header" style="margin-bottom:0;">
                <div>
                    <h2 style="margin:0;">{{ __('Select a child') }}</h2>
                    <p class="muted">{{ __('You can switch only between student accounts that have verified your relationship.') }}</p>
                </div>
                @if($children->count() > 1)
                    <form method="GET" action="{{ route('guardian.dashboard') }}" style="display:flex;gap:8px;align-items:center;">
                        <label for="student_id_select" class="muted">{{ __('Child') }}</label>
                        <select id="student_id_select" name="student_id" onchange="this.form.submit()" style="min-width:190px;">
                            @foreach($children as $child)
                                <option value="{{ $child->id }}" @selected($selectedStudent?->id === $child->id)>{{ $child->name }}</option>
                            @endforeach
                        </select>
                    </form>
                @endif
            </div>
            <div class="grid grid-3" style="margin-top:14px;">
                @foreach($children as $child)
                    <a href="{{ route('guardian.dashboard', ['student_id' => $child->id]) }}" class="course-item" style="text-decoration:none;border:1px solid {{ $selectedStudent?->id === $child->id ? 'var(--primary)' : 'var(--border)' }};">
                        <strong>{{ $child->name }}</strong>
                        <span class="muted">{{ __('Student ID') }}: {{ $child->id }}</span>
                        <small class="muted">{{ $child->branch?->name ?? __('No branch assigned') }}</small>
                    </a>
                @endforeach
            </div>
        </div>

        @if($selectedStudent)
            <div class="panel" style="margin-bottom:18px;">
                <div class="page-header" style="margin-bottom:0;">
                    <div>
                        <span class="badge success">{{ __('Student-approved access') }}</span>
                        <h2 style="margin:8px 0 0;">{{ $selectedStudent->name }}</h2>
                        <p class="muted">{{ __('Student ID') }}: {{ $selectedStudent->id }} · {{ $selectedStudent->branch?->name ?? __('No branch assigned') }}</p>
                    </div>
                </div>
                <div class="dash-summary" style="margin-top:14px;">
                    <div class="summary-box"><span class="muted">{{ __('Active enrollments') }}</span><strong>{{ $enrollments->where('status', 'active')->count() }}</strong></div>
                    <div class="summary-box"><span class="muted">{{ __('Attendance') }}</span><strong>{{ $attendancePresent }}/{{ $attendanceTotal }}</strong></div>
                    <div class="summary-box"><span class="muted">{{ __('Lesson progress') }}</span><strong>{{ $progressPercent }}%</strong></div>
                    <div class="summary-box"><span class="muted">{{ __('Outstanding fees') }}</span><strong>৳{{ number_format($unpaidAmount, 2) }}</strong></div>
                </div>
            </div>

            <div class="grid grid-2">
                <div class="panel">
                    <h2 style="margin-top:0;">{{ __('Courses and learning progress') }}</h2>
                    @forelse($enrollments as $enrollment)
                        <div class="course-item" style="margin-bottom:10px;">
                            <strong>{{ $enrollment->course?->title ?? __('Course') }}</strong>
                            <div class="muted">{{ __('Batch') }}: {{ $enrollment->batch?->title ?? '—' }}</div>
                            <small class="muted">{{ __('Status') }}: {{ ucfirst($enrollment->status) }}</small>
                        </div>
                    @empty
                        <p class="muted">{{ __('No course enrollments are linked to this student.') }}</p>
                    @endforelse
                    <p class="muted">{{ __('Completed lessons') }}: {{ $completedLessons }} / {{ $totalLessons }}</p>
                </div>

                <div class="panel">
                    <h2 style="margin-top:0;">{{ __('Attendance history') }}</h2>
                    @forelse($attendance as $record)
                        <div class="course-item" style="margin-bottom:8px;">
                            <strong>{{ $record->batch?->title ?? __('Batch') }}</strong>
                            <span class="badge {{ $record->status === 'present' ? 'success' : 'warning' }}">{{ ucfirst($record->status) }}</span>
                            <small class="muted">{{ $record->attendance_date?->format('d M Y') }}</small>
                        </div>
                    @empty
                        <p class="muted">{{ __('No attendance records are available.') }}</p>
                    @endforelse
                </div>

                <div class="panel">
                    <h2 style="margin-top:0;">{{ __('Exam results') }}</h2>
                    @forelse($examAttempts as $attempt)
                        <div class="course-item" style="margin-bottom:8px;">
                            <strong>{{ $attempt->exam?->title ?? __('Exam') }}</strong>
                            <div class="muted">{{ $attempt->exam?->batch?->course?->title ?? '' }}</div>
                            <span class="badge info">{{ __('Score') }}: {{ $attempt->score ?? '—' }}</span>
                            <small class="muted">{{ $attempt->submitted_at?->format('d M Y H:i') }}</small>
                        </div>
                    @empty
                        <p class="muted">{{ __('No submitted exam results are available.') }}</p>
                    @endforelse
                </div>

                <div class="panel">
                    <h2 style="margin-top:0;">{{ __('Assignments and grading') }}</h2>
                    @forelse($assignmentSubmissions as $submission)
                        <div class="course-item" style="margin-bottom:8px;">
                            <strong>{{ $submission->assignment?->title ?? __('Assignment') }}</strong>
                            <div class="muted">{{ $submission->assignment?->batch?->course?->title ?? '' }}</div>
                            <span class="badge info">{{ __('Status') }}: {{ ucfirst($submission->status) }}</span>
                            @if($submission->score !== null)<small class="muted">{{ __('Score') }}: {{ $submission->score }}</small>@endif
                            @if($submission->feedback)<p class="muted">{{ $submission->feedback }}</p>@endif
                        </div>
                    @empty
                        <p class="muted">{{ __('No assignment submissions are available.') }}</p>
                    @endforelse
                </div>

                <div class="panel">
                    <h2 style="margin-top:0;">{{ __('Invoices and fees') }}</h2>
                    @forelse($invoices as $invoice)
                        <div class="course-item" style="margin-bottom:8px;">
                            <strong>{{ $invoice->invoice_number }} · {{ $invoice->title }}</strong>
                            <div class="muted">{{ __('Amount') }}: ৳{{ number_format((float) $invoice->amount, 2) }}</div>
                            <span class="badge {{ $invoice->status === 'paid' ? 'success' : 'warning' }}">{{ ucfirst($invoice->status) }}</span>
                            <small class="muted">{{ __('Due date') }}: {{ $invoice->due_date?->format('d M Y') ?? '—' }}</small>
                        </div>
                    @empty
                        <p class="muted">{{ __('No invoices are available.') }}</p>
                    @endforelse
                </div>

                <div class="panel">
                    <h2 style="margin-top:0;">{{ __('Payment records') }}</h2>
                    @forelse($payments as $payment)
                        <div class="course-item" style="margin-bottom:8px;">
                            <strong>৳{{ number_format((float) $payment->amount, 2) }}</strong>
                            <div class="muted">{{ $payment->course?->title ?? $payment->batch?->title ?? __('Course payment') }}</div>
                            <span class="badge {{ $payment->status === 'paid' ? 'success' : 'warning' }}">{{ ucfirst($payment->status) }}</span>
                            <small class="muted">{{ ucfirst($payment->method ?? '—') }} · {{ $payment->created_at?->format('d M Y') }}</small>
                        </div>
                    @empty
                        <p class="muted">{{ __('No payment records are available.') }}</p>
                    @endforelse
                </div>

                <div class="panel">
                    <h2 style="margin-top:0;">{{ __('Upcoming live classes') }}</h2>
                    @forelse($liveClasses as $liveClass)
                        <div class="course-item" style="margin-bottom:8px;">
                            <strong>{{ $liveClass->title }}</strong>
                            <div class="muted">{{ $liveClass->batch?->course?->title ?? $liveClass->batch?->title }}</div>
                            <small class="muted">{{ $liveClass->starts_at?->format('d M Y H:i') }}</small>
                            @if($liveClass->meeting_url && preg_match('/^https?:\/\//i', $liveClass->meeting_url))
                                <p><a href="{{ $liveClass->meeting_url }}" target="_blank" rel="noopener noreferrer">{{ __('Open class link') }}</a></p>
                            @endif
                            @if($liveClass->recording_url && preg_match('/^https?:\/\//i', $liveClass->recording_url))
                                <p><a href="{{ $liveClass->recording_url }}" target="_blank" rel="noopener noreferrer">{{ __('View recording') }}</a></p>
                            @endif
                        </div>
                    @empty
                        <p class="muted">{{ __('No upcoming live classes are available.') }}</p>
                    @endforelse
                </div>
            </div>
        @endif
    @endif
@endsection
