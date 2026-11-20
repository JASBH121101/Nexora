@extends('layouts.app')

@section('title', 'Exportaciones · NEXORA')

@section('content')
<section class="panel">
    <div class="page-head">
        <div>
            <p class="muted eyebrow">NEXORA EXPORT ENGINE</p>
            <h1>Exportar catálogo</h1>
            <p class="muted">Descarga una copia de los datos autorizados de tu catálogo.</p>
        </div>
        <a class="secondary-button link" href="{{ route('admin.home') }}">Volver</a>
    </div>

    @if ($errors->any())
        <div class="notice error" role="alert">
            <strong>Revisa la exportación.</strong>
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    @if ($canExport)
        <form method="POST" action="{{ route('admin.exports.store') }}" class="export-form">
            @csrf
            <div class="form-grid">
                <label>Formato
                    <select name="format" required>
                        <option value="csv" @selected(old('format') === 'csv')>CSV</option>
                        <option value="json" @selected(old('format') === 'json')>JSON</option>
                        <option value="xml" @selected(old('format') === 'xml')>XML</option>
                        @if ($zipAvailable)
                            <option value="zip" @selected(old('format') === 'zip')>ZIP · datos + fotografías originales</option>
                        @else
                            <option value="zip" disabled>ZIP · requiere PHP ZipArchive</option>
                        @endif
                        <option disabled>PDF · requiere biblioteca PDF (pendiente)</option>
                        <option disabled>XLSX · requiere biblioteca de hojas de cálculo (pendiente)</option>
                    </select>
                </label>

                <label>Contenido
                    <select name="status" required>
                        <option value="published" @selected(old('status', 'published') === 'published')>Solo publicados</option>
                        @if (auth()->user()->canAccessField('products', 'status', 'view'))
                            <option value="draft" @selected(old('status') === 'draft')>Solo borradores</option>
                            <option value="all" @selected(old('status') === 'all')>Todos los estados</option>
                        @endif
                    </select>
                </label>

                @if ($categories->isNotEmpty())
                    <label>Categoría
                        <select name="category_id">
                            <option value="">Todas las categorías autorizadas</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected((string) old('category_id') === (string) $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif

                <label>IDs de productos (opcional)
                    <input name="product_ids" value="{{ old('product_ids') }}" placeholder="Ej. 12,18,25">
                    <small>Escribe los identificadores separados por comas para limitar la exportación.</small>
                </label>

                <label>Claves de campos dinámicos (opcional)
                    <input name="field_keys" value="{{ old('field_keys') }}" placeholder="Ej. brand,model,ram">
                    <small>Vacío = todos los campos dinámicos que tu cuenta puede consultar.</small>
                </label>
            </div>

            <fieldset>
                <legend>Datos adicionales</legend>
                @if ($canViewPrices)
                    <label class="check-option">
                        <input type="checkbox" name="include_prices" value="1" @checked(old('include_prices'))>
                        <span>Incluir precios y monedas</span>
                    </label>
                @endif
                @if ($canViewInventory)
                    <label class="check-option">
                        <input type="checkbox" name="include_inventory" value="1" @checked(old('include_inventory'))>
                        <span>Incluir cantidades de inventario básico</span>
                    </label>
                @endif
                @if ($canViewPhotos)
                    <label class="check-option">
                        <input type="checkbox" name="include_photos" value="1" @checked(old('include_photos'))>
                        <span>Incluir referencias de fotografías{{ $zipAvailable ? ' y archivos originales en ZIP' : '' }}</span>
                    </label>
                @endif
            </fieldset>

            <div class="note">
                Se exportan únicamente los campos visibles para tu usuario. CSV, JSON y XML incluyen rutas de fotografías, no imágenes binarias; ZIP puede incorporar los archivos originales. PDF y XLSX permanecen deshabilitados hasta aprobar e integrar dependencias compatibles con el VPS.
            </div>

            <button class="primary-button" type="submit">Generar descarga</button>
        </form>
    @else
        <div class="notice error">Tu cuenta puede crear exportaciones, pero no tiene autorización para descargarlas.</div>
    @endif
</section>

@if ($canViewHistory)
    <section class="panel">
        <div class="page-head">
            <div>
                <p class="muted eyebrow">AUDITORÍA</p>
                <h2>Historial de exportaciones</h2>
                <p class="muted">Últimas 50 ejecuciones registradas.</p>
            </div>
        </div>

        @forelse ($history as $item)
            <article class="history-row">
                <div>
                    <strong>{{ strtoupper($item->format) }} · {{ $item->file_name }}</strong>
                    <small>{{ $item->user?->name ?? 'Usuario eliminado' }} · {{ $item->created_at?->format('Y-m-d H:i') }}</small>
                </div>
                <div class="history-meta">
                    <span class="state {{ $item->status === 'completed' ? 'success' : 'failed' }}">{{ $item->status === 'completed' ? 'Completada' : 'Fallida' }}</span>
                    <small>{{ number_format($item->record_count) }} registros · {{ $item->file_size_bytes === null ? '—' : number_format($item->file_size_bytes).' bytes' }}</small>
                </div>
            </article>
        @empty
            <p class="muted">Todavía no hay exportaciones registradas.</p>
        @endforelse
    </section>
@else
    <p class="muted history-locked">Tu cuenta no tiene permiso para consultar el historial de exportaciones.</p>
@endif
@endsection

@push('head')
<style>
    .page-head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px}.eyebrow{font-size:11px;font-weight:800;letter-spacing:.12em}.page-head h1,.page-head h2{margin:8px 0}.link{display:inline-flex;align-items:center;text-decoration:none}.primary-button,.secondary-button{min-height:40px;padding:0 15px;border:0;border-radius:9px;font:inherit;font-size:12px;font-weight:800;cursor:pointer}.primary-button{background:#20271f;color:#fff}.secondary-button{background:#eef1e9;color:#35402b}.muted{color:#697366}.small,small{display:block;color:#778171;font-size:11px;line-height:1.45;margin-top:5px}
    .form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.export-form label{display:block;font-size:12px;font-weight:750}.export-form input:not([type=checkbox]),.export-form select{display:block;width:100%;min-height:40px;margin-top:6px;padding:9px 11px;border:1px solid #dfe5d9;border-radius:9px;background:#fff;color:#20271f;font:inherit;font-size:13px}.export-form fieldset{display:flex;flex-wrap:wrap;gap:14px;margin:18px 0;padding:14px;border:1px solid #e6eae2;border-radius:11px}.export-form legend{padding:0 6px;font-size:12px;font-weight:800}.check-option{display:flex!important;align-items:center;gap:7px}.check-option input{accent-color:#34472d}.note,.notice{margin:15px 0;padding:12px 14px;border-radius:10px;background:#f4f6ef;color:#56614d;font-size:12px;line-height:1.55}.notice.error{background:#fff0ee;color:#963b32}.primary-button{margin-top:4px}
    .history-row{display:flex;justify-content:space-between;gap:12px;padding:13px 0;border-bottom:1px solid #edf0e9}.history-row strong{font-size:12px}.history-meta{text-align:right}.state{display:inline-block;padding:4px 8px;border-radius:20px;font-size:10px;font-weight:800}.state.success{background:#edf7ed;color:#286536}.state.failed{background:#fff0ee;color:#963b32}.history-locked{font-size:12px}
    @media(max-width:600px){.form-grid{grid-template-columns:1fr}.page-head{align-items:center}.history-row{align-items:flex-start;flex-direction:column}.history-meta{text-align:left}}
</style>
@endpush
