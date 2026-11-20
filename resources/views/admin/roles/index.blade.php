@extends('layouts.app')

@section('title', 'Roles y permisos · NEXORA')

@section('content')
<section class="panel">
    <div class="page-head">
        <div>
            <p class="muted eyebrow">NEXORA CORE · PERMISSION ENGINE</p>
            <h1>Roles y permisos</h1>
            <p class="muted">Define qué módulos, acciones y campos puede utilizar cada cargo.</p>
        </div>
        <a class="secondary-button link" href="{{ route('admin.employees.index') }}">Empleados</a>
    </div>

    @if (session('status'))
        <div class="notice success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="notice error">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <div class="subpanel">
        <h2>Crear rol personalizado</h2>
        <form method="POST" action="{{ route('admin.roles.store') }}">
            @csrf
            <div class="role-head-fields">
                <label>Nombre del rol
                    <input name="name" maxlength="80" value="{{ old('name') }}" required placeholder="Ej. Auxiliar de bodega">
                </label>
                <label>Descripción
                    <input name="description" maxlength="255" value="{{ old('description') }}" placeholder="Responsabilidades del cargo">
                </label>
            </div>
            <h3>Permisos por módulo y acción</h3>
            @include('admin.roles._permission-fields', [
                'permissionModules' => $permissionModules,
                'productFieldDefinitions' => $productFieldDefinitions,
                'inventoryFieldDefinitions' => $inventoryFieldDefinitions,
                'permissions' => [],
                'fieldPermissions' => [],
            ])
            <button class="primary-button" type="submit">Crear rol</button>
        </form>
    </div>

    <div class="role-list">
        <h2>Roles disponibles</h2>
        @foreach ($roles as $role)
            <article class="role-card">
                <form method="POST" action="{{ route('admin.roles.update', $role) }}">
                    @csrf
                    @method('PUT')
                    <div class="role-head-fields">
                        <label>Nombre
                            <input name="name" maxlength="80" value="{{ $role->name }}" required>
                        </label>
                        <label>Descripción
                            <input name="description" maxlength="255" value="{{ $role->description }}">
                        </label>
                    </div>
                    <div class="role-meta">
                        <strong>{{ $role->is_system ? 'Rol predefinido' : 'Rol personalizado' }}</strong>
                        <span>{{ $role->users()->count() }} empleado(s) asignado(s)</span>
                    </div>
                    <h3>Permisos por módulo y acción</h3>
                    @include('admin.roles._permission-fields', [
                        'permissionModules' => $permissionModules,
                        'productFieldDefinitions' => $productFieldDefinitions,
                        'inventoryFieldDefinitions' => $inventoryFieldDefinitions,
                        'permissions' => $role->permissions ?? [],
                        'fieldPermissions' => $role->field_permissions ?? [],
                    ])
                    <button class="secondary-button" type="submit">Guardar cambios del rol</button>
                </form>
            </article>
        @endforeach
    </div>
</section>
@endsection

@push('head')
<style>
.page-head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px}.page-head h1{margin:8px 0}.eyebrow{font-size:11px;font-weight:800;letter-spacing:.12em}.link{display:inline-flex;align-items:center;text-decoration:none}
.subpanel,.role-card{margin-top:18px;padding:16px;border:1px solid #e6eae2;border-radius:12px}.subpanel h2,.role-list>h2{font-size:16px;margin:0 0 13px}.role-card h3,.subpanel h3{font-size:13px;margin:18px 0 9px}
.role-head-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.role-head-fields label{display:grid;gap:7px;font-size:12px;font-weight:700}.role-head-fields input{min-height:40px;padding:9px;border:1px solid #dfe5d8;border-radius:9px;font:inherit}
.role-meta{display:flex;justify-content:space-between;gap:10px;margin-top:10px;color:#687363;font-size:11px}.permission-module{margin:12px 0;padding:12px;border:1px solid #e7ebe3;border-radius:10px}.permission-module h4{margin:0 0 9px;font-size:12px}.permission-actions{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:7px}.permission-actions label{display:flex;align-items:center;gap:7px;font-size:11px}.permission-actions input{accent-color:#6d8b38}
.field-rules{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.field-rule{display:grid;grid-template-columns:minmax(0,1fr) 105px 105px;align-items:center;gap:7px;padding:8px;border:1px solid #edf0e9;border-radius:8px;font-size:11px}.field-rule select{min-width:0;padding:7px;border:1px solid #dfe5d8;border-radius:7px;font:inherit}
.primary-button,.secondary-button{min-height:38px;padding:0 13px;border:0;border-radius:9px;font:inherit;font-size:12px;font-weight:800;cursor:pointer}.primary-button{margin-top:14px;background:#20271f;color:white}.secondary-button{margin-top:14px;background:#eef1e9;color:#35402b}.notice{margin:13px 0;padding:12px;border-radius:10px;font-size:13px}.success{background:#edf7ed;color:#286536}.error{background:#fff0ee;color:#963b32}
@media(max-width:650px){.page-head{flex-direction:column}.role-head-fields,.field-rules{grid-template-columns:1fr}.permission-actions{grid-template-columns:repeat(2,minmax(0,1fr))}.field-rule{grid-template-columns:minmax(0,1fr) 90px 90px}.role-meta{flex-direction:column}.subpanel,.role-card{padding:12px}}
</style>
@endpush
