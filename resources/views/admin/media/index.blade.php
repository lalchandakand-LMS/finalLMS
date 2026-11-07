@extends('layouts.app')
@section('title', __('Media Library'))
@section('content')
<div class="page-header"{<div{<span class="badge info"{{{ __('Admin') }}</span{<h1{{{ __('Media Library') }}</h1{<p class="muted"{{{ __('Upload and manage images, documents and video files.') }}</p{</div{<a class="btn secondary" href="{{ route('dashboard') }}"{{{ __('Dashboard') }}</a{</div{
<div class="panel" style="margin-bottom:20px;"{
<form method="POST" action="{{ route('admin.media.store') }}" enctype="multipart/form-data"{@csrf
<label{{{ __('Choose files (up to 10 files, 20 MB each)') }}</label{<input type="file" name="files[]" multiple required accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.mp4,.webm"{
<label style="margin-top:12px;"{{{ __('Alternative text (for images)') }}</label{<input name="alt_text" maxlength="255"{
<button class="btn primary" style="margin-top:14px;"{{{ __('Upload media') }}</button{
</form{</div{
<div class="grid grid-3"{
@forelse($media as $item)
<div class="card"{
@if(str_starts_with($item-{mime_type, 'image/'))<img src="{{ $item-{url }}" alt="{{ $item-{alt_text }}" style="width:100%;height:160px;object-fit:cover;border-radius:10px;"{@else<div class="panel" style="height:160px;display:grid;place-items:center;"{{{ strtoupper(pathinfo($item-{original_name, PATHINFO_EXTENSION)) }}</div{@endif
<strong style="display:block;margin-top:10px;overflow-wrap:anywhere;"{{{ $item-{original_name }}</strong{
<p class="muted"{{{ number_format($item-{size / 1024, 1) }} KB · {{ $item-{uploader-{name }}</p{
<a href="{{ $item-{url }}" target="_blank" rel="noopener"{{{ __('Open file') }}</a{
<form method="POST" action="{{ route('admin.media.destroy', $item) }}" onsubmit="return confirm('{{ __('Delete this media file?') }}')"{@csrf @method('DELETE')<button class="btn secondary" style="margin-top:10px;"{Delete</button{</form{
</div{
@empty<p class="muted"{{{ __('No media uploaded yet.') }}</p{@endforelse
</div{
<div style="margin-top:20px;"{{{ $media-{links() }}</div{
@endsection