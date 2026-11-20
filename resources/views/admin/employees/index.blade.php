@extends('layouts.app')

@section('title', 'Empleados · NEXORA')

@section('content')
<section class="panel">
    <div class="page-head">
        <div>
            <p class="muted eyebrow">GESTIÓN DE ACCESO</p>
            <h1>Empleados</h1>
            <p class="muted">Crea cuentas, asigna roles y configura excepciones individuales.</p>
        </div>
        <div class="head-actions">
            <a class="secondary-button link" href="{{ route('admin.roles.index') }}">Roles y permisos</a>
            <a class="secondary-button link" href="{{ route('admin.home') }}">Volver</a>
        </div>
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
    @if (session('employee_credentials_once'))
        <div class="notice credentials">
            <strong>Guarda estos datos ahora; la contraseña temporal no volverá a mostrarse.</strong>
            <p>Usuario: <code>{{ session('employee_credentials_once.username') }}</code></p>
            <p>Contraseña: <code>{{ session('employee_credentials_once.password') }}</code></p>
            <p>El empleado deberá cambiarla al ingresar.</p>
        </div>
    @endif

    <div class="subpanel">
        <h2>Crear cuenta</h2>
        <form method="POST" action="{{ route('admin.employees.store') }}" class="employee-form">
            @csrf
            <label>Nombre completo
                <input name="name" maxlength="100" value="{{ old('name') }}" required>
            </label>
            <label>Nombre de usuario
                <input name="username" minlength="3" maxlength="32" pattern="[A-Za-z0-9._-]+" value="{{ old('username') }}" required>
            </label>
            <label>Correo para recuperación
                <input name="email" type="email" maxlength="255" value="{{ old('email') }}" required>
            </label>
            <label>Rol inicial
                <select name="role_id">
                    <option value="">Sin rol (sin permisos de módulo)</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}" @selected((string) old('role_id') === (string) $role->id)>{{ $role->name }}</option>
                    @endforeach
                </select>
            </label>
            <button class="primary-button" type="submit">Crear empleado</button>
        </form>
        <p class="muted small">El correo no sirve para iniciar sesión. La contraseña temporal se muestra una sola vez.</p>
    </div>

    <div class="employee-list">
        <h2>Cuentas registradas</h2>
        @forelse ($employees as $employee)
            <article class="employee-card">
                <div class="employee-row">
                    <div>
                        <strong>{{ $employee->name }}</strong>
                        <p class="muted small">{{ $employee->username }} · {{ $employee->email }}</p>
                    </div>
                    <div class="actions">
                        <span class="{{ $employee->is_active ? 'active' : 'inactive' }}">
                            {{ $employee->is_active ? 'Activa' : 'Suspendida' }}
                        </span>
                        <form method="POST" action="{{ route('admin.employees.status', $employee) }}">
                            @csrf
                            <input type="hidden" name="is_active" value="{{ $employee->is_active ? '0' : '1' }}">
                            <button class="secondary-button compact" type="submit">
                                {{ $employee->is_active ? 'Suspender' : 'Activar' }}
                            </button>
                        </form>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.employees.role', $employee) }}" class="role-assignment">
                    @csrf
                    <label>Rol asignado
                        <select name="role_id">
                            <option value="">Sin rol</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}" @selected((string) $employee->role_id === (string) $role->id)>{{ $role->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button class="secondary-button compact" type="submit">Guardar rol</button>
                </form>

                <details class="permission-details">
                    <summary>Excepciones individuales</summary>
                    <form method="POST" action="{{ route('admin.employees.permissions', $employee) }}">
                        @csrf
                        <p class="muted small">Estas reglas prevalecen sobre el rol: Permitir o Denegar. Heredar conserva la configuración del rol.</p>
                        @foreach ($permissionModules as $moduleKey => $module)
                            <section class="override-module">
                                <h3>{{ $module['label'] }}</h3>
                                <div class="override-grid">
                                    @foreach ($module['permissions'] as $action => $label)
                                        @php($permissionKey = $moduleKey.'.'.$action)
                                        @php($permissionInputKey = str_replace('.', '__', $permissionKey))
                                        <label>{{ $label }}
                                            <select name="permission_overrides[permissions][{{ $permissionInputKey }}]">
                                                <option value="">Heredar</option>
                                                <option value="allow" @selected(($employee->permission_overrides['permissions'][$permissionKey] ?? null) === 'allow')>Permitir</option>
                                                <option value="deny" @selected(($employee->permission_overrides['permissions'][$permissionKey] ?? null) === 'deny')>Denegar</option>
                                            </select>
                                        </label>
                                    @endforeach
                                </div>
                            </section>
                        @endforeach

                        <section class="override-module">
                            <h3>Excepciones por campo de producto</h3>
                            <div class="field-rules">
                                @foreach ($productFieldDefinitions as $fieldKey => $fieldLabel)
                                    <div class="field-rule">
                                        <strong>{{ $fieldLabel }}</strong>
                                        @foreach (['view' => 'Lectura', 'edit' => 'Edición'] as $action => $actionLabel)
                                            <label>
                                                <span class="sr-only">{{ $actionLabel }} · {{ $fieldLabel }}</span>
                                                <select name="permission_overrides[fields][products][{{ $fieldKey }}][{{ $action }}]">
                                                    <option value="">Heredar</option>
                                                    <option value="allow" @selected(data_get($employee->permission_overrides, 'fields.products.'.$fieldKey.'.'.$action) === 'allow')>Permitir</option>
                                                    <option value="deny" @selected(data_get($employee->permission_overrides, 'fields.products.'.$fieldKey.'.'.$action) === 'deny')>Denegar</option>
                                                </select>
                                            </label>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        </section>

                        <section class="override-module">
                            <h3>Excepciones por campo de inventario</h3>
                            <div class="field-rules">
                                @foreach ($inventoryFieldDefinitions as $fieldKey => $fieldLabel)
                                    <div class="field-rule">
                                        <strong>{{ $fieldLabel }}</strong>
                                        @foreach (['view' => 'Lectura', 'edit' => 'Edición'] as $action => $actionLabel)
                                            <label>
                                                <span class="sr-only">{{ $actionLabel }} · {{ $fieldLabel }}</span>
                                                <select name="permission_overrides[fields][inventory][{{ $fieldKey }}][{{ $action }}]">
                                                    <option value="">Heredar</option>
                                                    <option value="allow" @selected(data_get($employee->permission_overrides, 'fields.inventory.'.$fieldKey.'.'.$action) === 'allow')>Permitir</option>
                                                    <option value="deny" @selected(data_get($employee->permission_overrides, 'fields.inventory.'.$fieldKey.'.'.$action) === 'deny')>Denegar</option>
                                                </select>
                                            </label>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        </section>
                        <button class="secondary-button save-permissions" type="submit">Guardar excepciones</button>
                    </form>
                </details>
            </article>
        @empty
            <p class="muted">Todavía no hay empleados registrados.</p>
        @endforelse
        {{ $employees->links() }}
    </div>
</section>
@endsection

@push('head')
<style>
.page-head{display:flex;justify-content:space-between;gap:12px}.head-actions{display:flex;gap:8px;flex-wrap:wrap}.eyebrow{font-size:11px;font-weight:800}.page-head h1{margin:8px 0}.link{display:inline-flex;align-items:center;text-decoration:none}
.subpanel{margin-top:22px;padding:16px;border:1px solid #e6eae2;border-radius:13px}.subpanel h2,.employee-list h2{font-size:16px;margin:0 0 12px}
.employee-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.employee-form label,.role-assignment label{display:grid;gap:7px;font-size:12px;font-weight:700}
.employee-form input,.employee-form select,.role-assignment select{min-height:42px;padding:10px;border:1px solid #dfe5d8;border-radius:9px;font:inherit}
.primary-button,.secondary-button{min-height:38px;padding:0 13px;border:0;border-radius:9px;font:inherit;font-size:12px;font-weight:800;cursor:pointer}.primary-button{background:#20271f;color:white}.secondary-button{background:#eef1e9;color:#35402b}.employee-form button{align-self:end}
.small{font-size:12px}.employee-list{margin-top:24px}.employee-card{padding:14px 0;border-bottom:1px solid #edf0e9}.employee-row{display:flex;align-items:center;justify-content:space-between;gap:12px}.employee-row p{margin:5px 0 0}
.actions{display:flex;align-items:center;gap:10px;font-size:11px;font-weight:800}.active{color:#347b43}.inactive{color:#a33d35}.compact{min-height:34px}.role-assignment{display:flex;align-items:end;gap:8px;margin-top:12px}.role-assignment label{min-width:210px}.role-assignment select{width:100%;min-width:0}
.permission-details{margin-top:12px;padding-top:10px;border-top:1px solid #edf0e9}.permission-details summary{font-size:12px;font-weight:800;cursor:pointer}.permission-details form{padding-top:10px}
.override-module{margin:12px 0;padding:12px;border:1px solid #e7ebe3;border-radius:10px}.override-module h3{font-size:12px;margin:0 0 9px}.override-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.override-grid label{display:grid;grid-template-columns:minmax(0,1fr) 110px;align-items:center;gap:7px;font-size:11px}.override-grid select,.field-rule select{min-width:0;padding:7px;border:1px solid #dfe5d8;border-radius:7px;font:inherit}
.field-rules{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.field-rule{display:grid;grid-template-columns:minmax(0,1fr) 95px 95px;align-items:center;gap:7px;padding:8px;border:1px solid #edf0e9;border-radius:8px;font-size:11px}
.save-permissions{min-height:34px}.notice{margin:13px 0;padding:12px;border-radius:10px;font-size:13px}.success,.credentials{background:#edf7ed;color:#286536}.error{background:#fff0ee;color:#963b32}.credentials code{overflow-wrap:anywhere}
@media(max-width:650px){.page-head,.employee-row{flex-direction:column;align-items:flex-start}.head-actions,.head-actions a{width:100%}.head-actions a{justify-content:center}.employee-form,.override-grid,.field-rules{grid-template-columns:1fr}.employee-form button{width:100%}.actions{width:100%;justify-content:space-between}.role-assignment{align-items:stretch;flex-direction:column}.role-assignment label{min-width:0}.role-assignment button{width:100%}.field-rule{grid-template-columns:minmax(0,1fr) 90px 90px}.subpanel{padding:12px}}
</style>
@endpush
