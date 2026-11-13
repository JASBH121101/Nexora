@extends('layouts.app')

@section('title', 'Preparación de instalación · NEXORA')

@section('content')
    <section class="panel">
        <p class="muted" style="margin:0;font-size:12px;font-weight:800;letter-spacing:.12em">SETUP ENGINE · FASE 1</p>
        <h1>Preparación de instalación</h1>
        <p class="muted">Antes de crear el catálogo, NEXORA comprueba que el VPS, PHP, la base de datos y los permisos estén preparados.</p>
        <div class="checks">
            @foreach ($checks as $check)
                <div class="check">
                    <div>
                        <strong>{{ $check['label'] }}</strong>
                        <small>{{ $check['detail'] }}</small>
                    </div>
                    <span class="status {{ $check['passed'] ? 'ok' : 'fail' }}">
                        {{ $check['passed'] ? 'Correcto' : 'Revisar' }}
                    </span>
                </div>
            @endforeach
        </div>
        @if (! $schemaReady)
            <div class="note">
                El esquema inicial de NEXORA todavía no está instalado. Después de configurar la conexión de base de datos, ejecuta
                <code>php artisan migrate --force</code> desde la terminal del VPS. El paquete instalable tendrá un asistente para guiar este proceso.
            </div>
        @elseif ($requirementsPassed)
            <div class="note">
                El entorno superó las comprobaciones iniciales. La siguiente fase habilitará el registro transaccional del propietario y el cierre seguro del instalador.
                Esta pantalla todavía no crea usuarios ni completa la instalación.
            </div>
        @else
            <div class="note">
                Corrige los requisitos marcados y vuelve a cargar esta página. NEXORA no continuará mientras falte una condición obligatoria.
            </div>
        @endif
    </section>
@endsection
