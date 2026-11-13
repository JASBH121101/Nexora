@extends('layouts.app')

@section('title', 'Acceso propietario · NEXORA')

@section('content')
    <section class="panel auth-panel">
        <p class="muted eyebrow">NEXORA ADMIN</p>
        <h1>Acceso del propietario</h1>
        <p class="muted">Ingresa con la cuenta propietaria creada durante la instalación.</p>

        @if ($errors->any())
            <div class="alert error" role="alert">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('admin.login.store') }}">
            @csrf
            <label for="email">Correo electrónico</label>
            <input id="email" name="email" type="email" autocomplete="username" maxlength="255"
                value="{{ old('email') }}" required autofocus>
            <label for="password">Contraseña</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>
            <label class="remember"><input type="checkbox" name="remember" value="1"> Mantener sesión iniciada</label>
            <button class="primary-button" type="submit">Ingresar al panel</button>
        </form>
    </section>
@endsection

@push('head')
<style>
    .auth-panel{max-width:480px;margin:34px auto 0}.eyebrow{font-size:12px;font-weight:800;letter-spacing:.12em}
    .auth-panel label{display:block;margin:16px 0 7px;font-size:13px;font-weight:700}
    .auth-panel input[type=email],.auth-panel input[type=password]{width:100%;min-height:44px;padding:11px 12px;border:1px solid #dfe5d8;border-radius:10px;font:inherit}
    .remember{display:flex!important;align-items:center;gap:8px}.remember input{width:16px;height:16px;accent-color:#89a92f}
    .primary-button{width:100%;min-height:44px;margin-top:12px;border:0;border-radius:10px;background:#20271f;color:#fff;font:inherit;font-weight:750}
    .alert{padding:12px 14px;border-radius:10px;font-size:13px}.error{background:#fff0ee;color:#963b32}.error p{margin:4px 0}
</style>
@endpush
