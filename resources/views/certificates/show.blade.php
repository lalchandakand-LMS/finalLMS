@extends('layouts.app')

@section('title', 'Certificate | LMS Academy')

@section('content')
    <div class="panel" style="max-width: 900px; margin: 30px auto; border: 2px solid #dfe7f6; background: linear-gradient(135deg, #fff, #f6f9ff);">
        <div style="text-align: center; margin-bottom: 24px;">
            <span class="badge info">Certificate of Completion</span>
            <h1 style="font-size: 3rem; margin: 12px 0; color: var(--primary-dark);">LMS Academy</h1>
        </div>

        <div style="text-align: center; margin-bottom: 20px;">
            <p style="font-size: 1.2rem; margin: 0;">This certifies that</p>
            <h2 style="font-size: 2.5rem; margin: 12px 0;">{{ $certificate->user->name }}</h2>
            <p style="font-size: 1.1rem; margin: 0;">has successfully completed</p>
            <h3 style="font-size: 2rem; margin: 12px 0;">{{ $certificate->course->title }}</h3>
        </div>

        <div class="meta-row" style="justify-content: center;">
            <span class="badge success">Certificate ID: {{ $certificate->certificate_number }}</span>
            <span class="badge warning">Issued: {{ $certificate->issued_at?->format('F j, Y') }}</span>
        </div>
    </div>
@endsection
