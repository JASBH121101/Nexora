@extends('layouts.app')

@section('title', 'Sitio y dominios · NEXORA')

@section('content')
<section class="panel">
    <div class="page-head">
        <div>
            <p class="muted eyebrow">NEXORA SITE ENGINE</p>
            <h1>Dominio del sitio</h1>
            <p class="muted">Conecta un dominio propio o un subdominio externo que controles.</p>
        </div>
        <a class="secondary-button link" href="{{ route('admin.home') }}">Volver al panel</a>
    </div>

    @if (session('status'))
        <div class="notice success" role="status">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="notice error" role="alert">
            <strong>No se pudo completar la operación.</strong>
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <div class="section-block">
        <h2>1. IP pública del alojamiento</h2>
        @if ($dnsTargets !== [])
            <p class="muted">NEXORA comprobará que los registros A o AAAA apunten a alguna de estas IP configuradas en el servidor:</p>
            <div class="target-list">
                @foreach ($dnsTargets as $target)
                    <code>{{ $target }}</code>
                @endforeach
            </div>
        @else
            <div class="notice warning">
                El servidor todavía no tiene <code>NEXORA_SITE_DNS_TARGETS</code> configurado. Solicita la IP pública del VPS al proveedor y agrégala al entorno del servidor antes de diagnosticar dominios.
            </div>
        @endif
        <p class="small">Puedes incluir IPv4 e IPv6 separadas por comas. NEXORA nunca modifica los registros DNS de tu proveedor.</p>
    </div>

    <div class="section-block">
        <h2>2. Agregar un nombre</h2>
        <form method="POST" action="{{ route('admin.site-domains.store') }}" class="domain-form">
            @csrf
            <label>
                Dominio o subdominio
                <input name="hostname" value="{{ old('hostname') }}" maxlength="253" placeholder="www.tunegocio.com" autocomplete="off" required>
                <small>Escribe solo el nombre, sin https://, rutas ni puertos.</small>
            </label>
            <label>
                Tipo
                <select name="type" required>
                    <option value="custom_domain" @selected(old('type', 'custom_domain') === 'custom_domain')>Dominio propio</option>
                    <option value="external_subdomain" @selected(old('type') === 'external_subdomain')>Subdominio gratuito de un proveedor externo</option>
                </select>
            </label>
            <button class="primary-button" type="submit">Agregar dominio</button>
        </form>
        <p class="small">Máximo cinco nombres por instalación. NEXORA no vende dominios ni ofrece subdominios centrales del tipo cliente.nexora.tld.</p>
    </div>
</section>

<section class="panel">
    <div class="section-heading">
        <div>
            <p class="muted eyebrow">VERIFICACIÓN</p>
            <h2>Dominios registrados</h2>
        </div>
        <span class="count">{{ count($domains) }} / 5</span>
    </div>

    @forelse ($domains as $domain)
        @php
            $status = $domain['status'] ?? 'pending';
            $typeLabel = ($domain['type'] ?? '') === 'external_subdomain' ? 'Subdominio externo' : 'Dominio propio';
            $diagnostics = (array) ($domain['diagnostics'] ?? []);
            $tls = (array) ($diagnostics['tls'] ?? []);
            $isPrimary = ($domain['id'] ?? null) === $primaryDomainId;
        @endphp
        <article class="domain-card">
            <div class="domain-header">
                <div class="domain-name">
                    <strong>{{ $domain['hostname'] ?? 'Dominio sin nombre' }}</strong>
                    <small>{{ $typeLabel }}</small>
                </div>
                <div class="status-group">
                    @if ($isPrimary)
                        <span class="pill primary">Principal</span>
                    @endif
                    <span class="pill {{ $status === 'ready' ? 'success' : 'pending' }}">{{ match ($status) {
                        'ready' => 'Verificado',
                        'target_not_configured' => 'Falta configurar VPS',
                        'dns_target_mismatch' => 'DNS pendiente',
                        'ownership_pending' => 'Propiedad pendiente',
                        'tls_invalid' => 'HTTPS pendiente',
                        default => 'Pendiente',
                    } }}</span>
                </div>
            </div>

            @if (isset($domain['verification']['txt_record_name'], $domain['verification']['token']))
                <div class="verification-block">
                    <p><strong>Registro TXT de propiedad</strong></p>
                    <small>Nombre / Host</small>
                    <code>{{ $domain['verification']['txt_record_name'] }}</code>
                    <small>Valor</small>
                    <code>{{ $domain['verification']['token'] }}</code>
                </div>
            @endif

            @if ($diagnostics !== [])
                <div class="diagnostic-grid">
                    <div><span>Destino DNS</span><strong>{{ !empty($diagnostics['expected_target_match']) ? 'Coincide' : 'Sin coincidencia' }}</strong></div>
                    <div><span>Registro TXT</span><strong>{{ !empty($diagnostics['txt_match']) ? 'Verificado' : 'Pendiente' }}</strong></div>
                    <div><span>Certificado TLS</span><strong>{{ ($tls['status'] ?? '') === 'valid' ? 'Válido' : 'No válido / no comprobado' }}</strong></div>
                </div>
                @if (!empty($diagnostics['dns_addresses']))
                    <p class="small">IP detectadas: {{ implode(', ', $diagnostics['dns_addresses']) }}</p>
                @endif
                @if (!empty($tls['valid_until']))
                    <p class="small">Certificado válido hasta: {{ $tls['valid_until'] }}</p>
                @endif
            @endif

            @if (!empty($domain['last_checked_at']))
                <p class="small">Última comprobación: {{ $domain['last_checked_at'] }}</p>
            @endif

            <div class="domain-actions">
                <form method="POST" action="{{ route('admin.site-domains.diagnose', $domain['id']) }}">
                    @csrf
                    <button class="secondary-button" type="submit">Comprobar DNS y HTTPS</button>
                </form>
                @if ($status === 'ready' && !$isPrimary)
                    <form method="POST" action="{{ route('admin.site-domains.primary', $domain['id']) }}">
                        @csrf
                        <button class="primary-button" type="submit">Usar como principal</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('admin.site-domains.destroy', $domain['id']) }}" onsubmit="return confirm('¿Eliminar este dominio de la instalación?')">
                    @csrf
                    @method('DELETE')
                    <button class="danger-button" type="submit">Eliminar</button>
                </form>
            </div>
        </article>
    @empty
        <div class="empty-state">
            <strong>No hay dominios registrados.</strong>
            <p>Agrega un dominio para obtener los registros DNS y el token de propiedad.</p>
        </div>
    @endforelse
</section>

<section class="panel guidance">
    <h2>Cómo conectar tu dominio</h2>
    <ol>
        <li>Configura en el proveedor DNS los registros A/AAAA hacia la IP pública del VPS indicado arriba.</li>
        <li>Agrega el registro TXT con el nombre y valor únicos que muestra NEXORA para ese dominio.</li>
        <li>Cuando la propagación DNS termine, pulsa “Comprobar DNS y HTTPS”.</li>
        <li>Configura un certificado válido para el nombre en tu alojamiento. La comprobación TLS solo lo valida; no emite ni renueva certificados.</li>
        <li>Cuando DNS, propiedad y TLS estén correctos, podrás seleccionarlo como dominio principal.</li>
    </ol>
    <p class="notice">HTTPS no se activa solo por escribir un dominio. El DNS, el servidor web, los puertos y el certificado deben estar configurados por el proveedor del VPS o mediante una herramienta ACME administrada por ti.</p>
    <p class="small">Por seguridad, la comprobación HTTPS conecta únicamente a las IP públicas configuradas en el servidor y solo al puerto 443. No se realizan solicitudes HTTP a destinos arbitrarios.</p>
</section>
@endsection

@push('head')
<style>
    .page-head,.section-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:14px}.page-head h1,.section-heading h2{margin:7px 0}.eyebrow{font-size:11px;font-weight:850;letter-spacing:.12em}.muted{color:#697366}.small,small{display:block;color:#778171;font-size:11px;line-height:1.5;margin-top:5px}.section-block{margin-top:22px;padding-top:19px;border-top:1px solid #edf0e9}.section-block h2,.guidance h2{font-size:16px;margin:0 0 12px}.target-list{display:flex;flex-wrap:wrap;gap:8px}.target-list code,.verification-block code{display:block;overflow-wrap:anywhere;padding:9px 10px;border-radius:8px;background:#f2f4ee;color:#35402b;font-size:12px}.domain-form{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(180px,.8fr) auto;align-items:end;gap:12px}.domain-form label{display:grid;gap:6px;font-size:12px;font-weight:750}.domain-form input,.domain-form select{width:100%;min-height:41px;padding:9px 11px;border:1px solid #dfe5d9;border-radius:9px;background:#fff;color:#20271f;font:inherit;font-size:13px}.primary-button,.secondary-button,.danger-button{min-height:39px;padding:0 13px;border:0;border-radius:9px;font:inherit;font-size:12px;font-weight:800;cursor:pointer;white-space:nowrap}.primary-button{background:#263526;color:#fff}.secondary-button{background:#eef1e9;color:#35402b}.danger-button{background:#fff0ee;color:#963b32}.link{display:inline-flex;align-items:center;text-decoration:none}.notice{margin:14px 0;padding:12px 14px;border-radius:10px;background:#f4f6ef;color:#56614d;font-size:12px;line-height:1.55}.notice.warning{background:#fff5dd;color:#75591c}.notice.error{background:#fff0ee;color:#963b32}.notice.success{background:#edf7ed;color:#286536}.count{padding:5px 9px;border-radius:20px;background:#eef1e9;color:#46523d;font-size:11px;font-weight:800}.domain-card{margin-top:14px;padding:16px;border:1px solid #e6eae2;border-radius:13px}.domain-header{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}.domain-name strong{font-size:15px;overflow-wrap:anywhere}.status-group{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:6px}.pill{padding:5px 9px;border-radius:20px;font-size:10px;font-weight:850}.pill.success{background:#edf7ed;color:#286536}.pill.pending{background:#fff5dd;color:#75591c}.pill.primary{background:#e9eef8;color:#344d78}.verification-block{display:grid;gap:6px;margin-top:14px;padding:12px;border-radius:10px;background:#fafbf8}.verification-block p{margin:0;font-size:12px}.diagnostic-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin-top:13px}.diagnostic-grid div{display:grid;gap:5px;padding:10px;border:1px solid #edf0e9;border-radius:9px}.diagnostic-grid span{color:#778171;font-size:10px}.diagnostic-grid strong{font-size:12px}.domain-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:14px}.domain-actions form{margin:0}.empty-state{padding:30px 15px;text-align:center;border:1px dashed #dfe5d8;border-radius:12px}.empty-state p{margin-bottom:0;color:#697366;font-size:12px}.guidance ol{padding-left:20px;color:#56614d;font-size:12px;line-height:1.8}.guidance li{padding-left:3px;margin:5px 0}
    @media(max-width:760px){.domain-form{grid-template-columns:1fr}.domain-form button{width:100%}.diagnostic-grid{grid-template-columns:1fr}.page-head{align-items:center}.domain-header{flex-direction:column}.status-group{justify-content:flex-start}.domain-actions{display:grid;grid-template-columns:1fr}.domain-actions button{width:100%}}
</style>
@endpush
