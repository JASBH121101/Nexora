@extends('layouts.app')

@section('title', $settings->business_name.' · Catálogo')

@section('content')
    <section class="panel">
        <p class="muted" style="margin:0;font-size:12px;font-weight:800;letter-spacing:.12em">CATÁLOGO PÚBLICO</p>
        <h1>{{ $settings->business_name }}</h1>
        <p class="muted">La instalación está configurada. El catálogo público y sus plantillas se habilitarán desde NEXORA Site Engine.</p>
    </section>
@endsection
