@extends('layouts.app')

@section('title', __('security.challenge_title').' · NEXORA')

@section('content')
<section class="panel challenge-panel">
    <p class="muted eyebrow">NEXORA ADMIN · MFA</p>
    <h1>{{ __('security.challenge_title') }}</h1>
    <p class="muted">{{ __('security.challenge_instructions') }}</p>

    @if ($errors->any())
        <div class="notice error" role="alert">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('admin.mfa.challenge.verify') }}">
        @csrf
        <label for="mfa-code">{{ __('security.code_label') }}</label>
        <input id="mfa-code" name="code" maxlength="32" autocomplete="one-time-code" autocapitalize="characters" required autofocus>
        <button class="primary-button" type="submit">{{ __('security.verify_button') }}</button>
    </form>

    <form method="POST" action="{{ route('admin.mfa.challenge.cancel') }}">
        @csrf
        <button class="secondary-button" type="submit">{{ __('security.cancel_login') }}</button>
    </form>
</section>
@endsection

@push('head')
<style>
    .challenge-panel{max-width:460px;margin:34px auto 0}.eyebrow{font-size:11px;font-weight:800;letter-spacing:.12em}.challenge-panel form{display:grid;gap:10px;margin-top:18px}.challenge-panel label{font-size:13px;font-weight:750}.challenge-panel input{width:100%;min-height:48px;padding:12px;border:1px solid #dfe5d8;border-radius:10px;font:inherit;letter-spacing:.12em}.primary-button,.secondary-button{min-height:44px;padding:0 14px;border:0;border-radius:10px;font:inherit;font-size:13px;font-weight:800;cursor:pointer}.primary-button{background:#20271f;color:#fff}.secondary-button{background:#eef1e9;color:#35402b}.notice{padding:12px;border-radius:10px;font-size:13px}.error{background:#fff0ee;color:#963b32}
</style>
@endpush
