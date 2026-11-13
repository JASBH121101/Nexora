@extends('layouts.app')

@section('title', 'Panel de administración · NEXORA')

@section('content')
    <section class="panel dashboard-panel">
        <div class="dashboard-heading">
            <div>
                <p class="muted eyebrow">NEXORA ADMIN</p>
                <h1>{{ $settings->business_name }}</h1>
                <p class="muted">Panel privado del propietario</p>
            </div>
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button class="secondary-button" type="submit">Cerrar sesión</button>
            </form>
        </div>

        @if (session('status'))
            <div class="notice success" role="status">{{ session('status') }}</div>
        @endif
        @if (session('verification_message'))
            <div class="notice">{{ session('verification_message') }}</div>
        @endif

        @if (! auth()->user()->hasVerifiedEmail())
            <div class="notice warning">
                El correo de propietario todavía no está verificado.
                <form method="POST" action="{{ route('verification.send') }}">
                    @csrf
                    <button class="text-button" type="submit">Solicitar enlace de verificación</button>
                </form>
            </div>
        @endif

        <div class="metrics">
            <article><span>PRODUCTOS</span><strong>{{ $productCount }}</strong></article>
            <article><span>PUBLICADOS</span><strong>{{ $publishedCount }}</strong></article>
            <article><span>PLANTILLA</span><strong>{{ ucfirst($settings->business_type) }}</strong></article>
        </div>

        <div class="notice">
            La cuenta propietaria y la configuración inicial ya están guardadas. Los módulos de gestión del catálogo se habilitarán en las siguientes entregas.
        </div>
    </section>
@endsection

@push('head')
<style>
    .dashboard-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:15px}.eyebrow{font-size:12px;font-weight:800;letter-spacing:.12em}.dashboard-heading h1{margin-top:8px}
    .secondary-button{min-height:40px;padding:0 14px;border:0;border-radius:10px;background:#eef1e9;color:#35402b;font:inherit;font-size:13px;font-weight:750}
    .metrics{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:24px 0}
    .metrics article{min-width:0;padding:16px;border:1px solid #e6eae2;border-radius:12px}.metrics span{display:block;color:#697366;font-size:10px;font-weight:800;letter-spacing:.08em}.metrics strong{display:block;margin-top:8px;font-size:23px;overflow-wrap:anywhere}
    .notice{margin:15px 0;padding:13px 15px;border-radius:11px;background:#f4f6ef;color:#56614d;font-size:13px;line-height:1.5}
    .success{background:#edf7ed;color:#286536}.warning{background:#fff5dd;color:#75591c}.text-button{padding:0;border:0;background:none;color:inherit;font:inherit;font-weight:800;text-decoration:underline;cursor:pointer}
    @media(max-width:560px){.dashboard-heading{flex-direction:column}.dashboard-heading form{width:100%}.dashboard-heading button{width:100%}.metrics{grid-template-columns:1fr}.metrics article{display:flex;align-items:center;justify-content:space-between;gap:10px}.metrics strong{margin:0;font-size:20px}}
</style>
@endpush
