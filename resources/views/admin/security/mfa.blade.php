@extends('layouts.app')

@section('title', __('security.title').' · NEXORA')

@section('content')
<section class="panel security-panel">
    <p class="muted eyebrow">NEXORA ADMIN · SEGURIDAD</p>
    <h1>{{ __('security.title') }}</h1>
    <p class="muted">{{ __('security.subtitle') }}</p>

    @if (session('status'))
        <div class="notice success" role="status">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="notice error" role="alert">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    @if (session('mfa_recovery_codes'))
        <section class="recovery-panel">
            <h2>{{ __('security.recovery_title') }}</h2>
            <p>{{ __('security.recovery_notice') }}</p>
            <ul class="recovery-codes">
                @foreach (session('mfa_recovery_codes') as $recoveryCode)
                    <li><code>{{ $recoveryCode }}</code></li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($enabled)
        <section class="state-card enabled">
            <strong>{{ __('security.enabled_status') }}</strong>
            <p>{{ __('security.remaining', ['count' => $unusedRecoveryCodes]) }}</p>
        </section>

        <form method="POST" action="{{ route('admin.security.mfa.disable') }}" class="mfa-form">
            @csrf
            <h2>{{ __('security.disable_title') }}</h2>
            <p class="muted">{{ __('security.disable_instructions') }}</p>
            <label for="disable-password">{{ __('security.current_password') }}</label>
            <input id="disable-password" name="password" type="password" autocomplete="current-password" required>
            <label for="disable-code">{{ __('security.code_label') }}</label>
            <input id="disable-code" name="code" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="one-time-code" required>
            <button class="danger-button" type="submit">{{ __('security.disable_button') }}</button>
        </form>
    @elseif ($pendingSecret)
        <section class="state-card pending">
            <strong>{{ __('security.pending_title') }}</strong>
            <p>{{ __('security.expires_notice') }}</p>
            <label for="manual-secret">{{ __('security.secret_label') }}</label>
            <code id="manual-secret" class="secret">{{ $pendingSecret }}</code>
            <p class="muted">{{ __('security.manual_instructions') }}</p>
            <label for="provisioning-uri">{{ __('security.provisioning_uri') }}</label>
            <textarea id="provisioning-uri" rows="3" readonly>{{ $provisioningUri }}</textarea>
        </section>

        <form method="POST" action="{{ route('admin.security.mfa.confirm') }}" class="mfa-form">
            @csrf
            <label for="confirm-code">{{ __('security.confirm_code') }}</label>
            <input id="confirm-code" name="code" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="one-time-code" required autofocus>
            <button class="primary-button" type="submit">{{ __('security.confirm_button') }}</button>
        </form>
    @else
        <section class="state-card">
            <strong>{{ __('security.disabled_status') }}</strong>
            <p>{{ __('security.disabled_instructions') }}</p>
        </section>
        <form method="POST" action="{{ route('admin.security.mfa.enrollment') }}" class="mfa-form">
            @csrf
            <h2>{{ __('security.start_title') }}</h2>
            <label for="start-password">{{ __('security.current_password') }}</label>
            <input id="start-password" name="password" type="password" autocomplete="current-password" required>
            <button class="primary-button" type="submit">{{ __('security.start_button') }}</button>
        </form>
    @endif

    <a class="back-link" href="{{ route('admin.home') }}">{{ __('security.back') }}</a>
</section>
@endsection

@push('head')
<style>
    .security-panel{max-width:620px;margin:30px auto 0}.eyebrow{font-size:11px;font-weight:800;letter-spacing:.12em}.state-card,.recovery-panel{margin:18px 0;padding:16px;border:1px solid #e4e9df;border-radius:12px;background:#fafbf8}.state-card strong{font-size:14px}.state-card p,.recovery-panel p{font-size:13px;line-height:1.55}.enabled{border-color:#b8d8bd;background:#f1f8f1}.pending{border-color:#e5d7a8;background:#fffaf0}.mfa-form{display:grid;gap:9px;margin:18px 0;padding:17px;border:1px solid #e6eae2;border-radius:12px}.mfa-form h2,.recovery-panel h2{margin:0;font-size:16px}.mfa-form label,.state-card label{font-size:12px;font-weight:750}.mfa-form input,.state-card textarea{width:100%;min-height:44px;padding:11px;border:1px solid #dfe5d8;border-radius:9px;font:inherit}.state-card textarea{height:auto;overflow-wrap:anywhere;font-size:11px}.secret{display:block;padding:12px;border-radius:9px;background:#f0f2ed;letter-spacing:.12em;overflow-wrap:anywhere}.primary-button,.danger-button{min-height:43px;padding:0 15px;border:0;border-radius:9px;background:#20271f;color:white;font:inherit;font-size:13px;font-weight:800;cursor:pointer}.danger-button{background:#963b32}.recovery-codes{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;padding:0;list-style:none}.recovery-codes li{padding:8px;border-radius:7px;background:#edf0e9;text-align:center}.recovery-codes code{font-weight:800;letter-spacing:.05em}.back-link{display:inline-block;margin-top:12px;color:#56614d;font-size:13px;font-weight:700}
    .notice{margin:12px 0;padding:12px 14px;border-radius:10px;font-size:13px}.success{background:#edf7ed;color:#286536}.error{background:#fff0ee;color:#963b32}
    @media(max-width:560px){.security-panel{padding:17px}.recovery-codes{grid-template-columns:1fr}}
</style>
@endpush
