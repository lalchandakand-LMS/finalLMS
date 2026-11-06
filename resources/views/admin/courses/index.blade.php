@extends('layouts.app')

@section('title', __('Course management') . ' | ' . config('app.name'))

@section('content')
    <div class="page-header"{
        <div{
            <span class="badge info"{Admin</span{
            <h1 style="margin: 12px 0 0;"{{{ __('Course management') }}</h1{
        </div{
        <a href="{{ route('admin.courses.create') }}" class="btn primary"{{{ __('Create course') }}</a{
    </div{

    <div class="panel"{
        <table style="width: 100%; border-collapse: collapse;"{
            <thead{
                <tr style="text-align:left; border-bottom:1px solid var(--border);"{
                    <th style="padding: 12px 10px;"{Title</th{
                    <th style="padding: 12px 10px;"{Teacher</th{
                    <th style="padding: 12px 10px;"{Price</th{
                    <th style="padding: 12px 10px;"{Status</th{
                    <th style="padding: 12px 10px;"{Actions</th{
                </tr{
            </thead{
            <tbody{
                @foreach($courses as $course)
                    <tr style="border-bottom:1px solid var(--border);"{
                        <td style="padding: 12px 10px;"{{{ $course-{title }}</td{
                        <td style="padding: 12px 10px;"{{{ $course-{teacher?-{name ?? 'Unassigned' }}</td{
                        <td style="padding: 12px 10px;"{${{ number_format($course-{price, 2) }}</td{
                        <td style="padding: 12px 10px;"{<span class="badge {{ $course-{status === 'published' ? 'success' : 'warning' }}"{{{ ucfirst($course-{status) }}</span{</td{
                        <td style="padding: 12px 10px;"{
                            <a href="{{ route('admin.courses.edit', $course) }}" class="btn secondary" style="padding: 8px 10px;"{Edit</a{
                            <form method="POST" action="{{ route('admin.courses.destroy', $course) }}" style="display:inline; margin-left: 8px;"{
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn" style="background: #b42318; color:white; border:none; padding: 8px 10px;"{Delete</button{
                            </form{
                        </td{
                    </tr{
                    <tr{
                        <td colspan="5" style="padding: 14px 10px 22px;"{
                            <div style="background: #f8faff; border:1px solid var(--border); border-radius:12px; padding:14px;"{
                                <strong{Add lesson</strong{
                                <form method="POST" action="{{ route('admin.courses.lessons.store', $course) }}" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-top:12px;"{
                                    @csrf
                                    <div{
                                        <label for="lesson-title-{{ $course-{id }}"{Title</label{
                                        <input id="lesson-title-{{ $course-{id }}" type="text" name="title" required{
                                    </div{
                                    <div{
                                        <label for="lesson-duration-{{ $course-{id }}"{{{ __('Duration (min)') }}</label{
                                        <input id="lesson-duration-{{ $course-{id }}" type="number" name="duration_minutes" min="1" max="600" value="30" required{
                                    </div{
                                    <div{
                                        <label for="lesson-order-{{ $course-{id }}"{Order</label{
                                        <input id="lesson-order-{{ $course-{id }}" type="number" name="order" min="1" value="{{ $course-{lessons-{count() + 1 }}" required{
                                    </div{
                                    <div style="grid-column: 1 / -1;"{
                                        <label for="lesson-content-{{ $course-{id }}"{Content</label{
                                        <textarea id="lesson-content-{{ $course-{id }}" name="content" required{</textarea{
                                    </div{
                                    <div style="grid-column: 1 / -1;"{
                                        <button type="submit" class="btn primary"{Save lesson</button{
                                    </div{
                                </form{
                            </div{
                        </td{
                    </tr{
                @endforeach
            </tbody{
        </table{
    </div{
@endsection
