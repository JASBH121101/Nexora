@extends('layouts.app')

@section('title', 'Instalación · NEXORA')

@section('content')
    <section class="panel">
        <p class="muted" style="margin:0;font-size:12px;font-weight:800;letter-spacing:.12em">NEXORA SETUP ENGINE</p>
        <h1>Prepara tu catálogo</h1>
        <p class="muted">Primero validamos el VPS y después configuramos el negocio, la cuenta propietaria, las monedas y la plantilla.</p>

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

        @if (session('status'))
            <div class="note" style="background:#edf7ed;color:#286536" role="status">{{ session('status') }}</div>
        @endif

        @if (! $schemaReady)
            @if ($errors->has('database'))
                <div class="note" style="background:#fff0ee;color:#963b32" role="alert">
                    {{ $errors->first('database') }}
                </div>
            @endif
            @if ($databaseConnected)
                <div class="note">
                    La conexión a la base de datos funciona. NEXORA puede crear sus tablas automáticamente.
                    Se aplicarán únicamente las migraciones pendientes; no se ejecutará ninguna eliminación de tablas.
                </div>
                <form method="POST" action="{{ route('setup.prepare-database') }}">
                    @csrf
                    <button class="primary-button" type="submit">Preparar base de datos automáticamente</button>
                </form>
            @else
                <div class="note">
                    Antes de crear las tablas, configura PostgreSQL en el entorno del VPS. EthioDeploy inyecta DATABASE_URL automáticamente.
                    Para una conexión manual, puedes definir DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME y DB_PASSWORD.
                    Usa una base de datos exclusiva para NEXORA y vuelve a cargar esta página para comprobar la conexión.
                </div>
            @endif
        @elseif (! $requirementsPassed)
            <div class="note">Corrige los requisitos marcados antes de continuar. No se creará ninguna cuenta mientras falte una condición obligatoria.</div>
        @else
            @if ($errors->any())
                <div class="note" style="background:#fff0ee;color:#963b32">
                    <strong>Revisa los datos del formulario.</strong>
                    <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <form id="nexora-setup-form" method="POST" action="{{ route('setup.store') }}" enctype="multipart/form-data" novalidate>
                @csrf
                <div class="wizard-progress">
                    <span class="wizard-current" id="wizard-current">Paso 1 de 4</span>
                    <div class="wizard-track"><span id="wizard-progress-bar"></span></div>
                </div>

                <section class="wizard-step" data-step="0">
                    <h2>1. Negocio y propietario</h2>
                    <label>Nombre comercial
                        <input name="business_name" maxlength="120" required value="{{ old('business_name') }}" placeholder="Ej. Mi tienda">
                    </label>
                    <label>Tipo de negocio
                        <select name="business_type" required>
                            <option value="">Selecciona una plantilla</option>
                            @foreach ($templates as $key => $label)
                                <option value="{{ $key }}" @selected(old('business_type') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Nombre del propietario
                        <input name="owner_name" maxlength="100" required value="{{ old('owner_name') }}" autocomplete="name">
                    </label>
                    <label>Nombre de usuario
                        <input name="owner_username" minlength="3" maxlength="32" pattern="[A-Za-z0-9._-]+" required value="{{ old('owner_username') }}" autocomplete="username">
                    </label>
                    <label>Correo del propietario (para recuperación)

                        <input name="owner_email" type="email" maxlength="255" required value="{{ old('owner_email') }}" autocomplete="email">
                    </label>
                    <div class="field-grid">
                        <label>Contraseña
                            <div class="password-control">
                                <input id="owner-password" name="owner_password" type="password" minlength="8" required autocomplete="new-password">
                                <button type="button" class="password-toggle" data-password-toggle aria-controls="owner-password" aria-label="Mostrar contraseña" aria-pressed="false">Mostrar</button>
                            </div>
                        </label>
                        <label>Confirmar contraseña
                            <div class="password-control">
                                <input id="owner-password-confirmation" name="owner_password_confirmation" type="password" minlength="8" required autocomplete="new-password">
                                <button type="button" class="password-toggle" data-password-toggle aria-controls="owner-password-confirmation" aria-label="Mostrar confirmación de contraseña" aria-pressed="false">Mostrar</button>
                            </div>
                        </label>
                    </div>
                    <p class="muted small">Usa al menos 8 caracteres. Puedes mostrar u ocultar cada campo para revisar lo que escribiste. La contraseña se almacenará con el hasher de Laravel.</p>
                </section>

                <section class="wizard-step" data-step="1" hidden>
                    <h2>2. Identidad y monedas</h2>
                    <label>Descripción del negocio
                        <textarea name="description" maxlength="700" rows="3" placeholder="Describe brevemente tu negocio">{{ old('description') }}</textarea>
                    </label>
                    <div class="field-grid">
                        <label>Color principal
                            <input name="primary_color" type="color" required value="{{ old('primary_color', '#89a92f') }}">
                        </label>
                        <label>Moneda principal
                            <select name="primary_currency" required>
                                @foreach ($currencies as $currency)
                                    <option value="{{ $currency }}" @selected(old('primary_currency', 'USD') === $currency)>{{ $currency }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    <p class="muted small">Monedas adicionales. Los precios se registrarán manualmente por moneda; NEXORA no hará conversiones automáticas.</p>
                    <div class="currency-options">
                        @foreach ($currencies as $currency)
                            @if ($currency !== 'USD' && $currency !== 'NIO')
                                <label><input type="checkbox" name="additional_currencies[]" value="{{ $currency }}" @checked(in_array($currency, old('additional_currencies', [])))> {{ $currency }}</label>
                            @endif
                        @endforeach
                        <label><input type="checkbox" name="additional_currencies[]" value="NIO" @checked(in_array('NIO', old('additional_currencies', [])))> NIO</label>
                    </div>

                    <div class="section-separator">
                        <div class="section-heading">
                            <div><h3>Contactos del negocio</h3><p class="muted small">Hasta cinco teléfonos. Puedes ocultar un contacto del sitio público.</p></div>
                            <button type="button" class="secondary-button" id="add-phone">Añadir teléfono</button>
                        </div>
                        <div id="phone-list"></div>
                    </div>
                    <input type="hidden" name="photos_enabled" value="0">
                    <label class="toggle-row"><input type="checkbox" name="photos_enabled" value="1" checked> Permitir fotografías en el catálogo</label>
                    <div class="field-grid">
                        <label>Logotipo (opcional)
                            <input type="file" name="logo" accept="image/png,image/jpeg,image/webp">
                        </label>
                        <label>Portada (opcional)
                            <input type="file" name="cover" accept="image/png,image/jpeg,image/webp">
                        </label>
                    </div>
                    <p class="muted small">Las imágenes se guardan en el VPS. Si desactivas las fotografías, el catálogo no aceptará imágenes de productos.</p>
                </section>

                <section class="wizard-step" data-step="2" hidden>
                    <h2>3. Plantilla y catálogo</h2>
                    <p class="muted">La plantilla cargará categorías y campos iniciales editables. Podrás cambiar sus etiquetas, agregar campos y reorganizar grupos desde el panel.</p>
                    <div class="template-summary" id="template-summary" aria-live="polite">Selecciona un tipo de negocio en el paso 1 para ver su plantilla.</div>
                    <div class="note">Los campos dinámicos se guardarán como definiciones y valores relacionados. NEXORA no creará columnas SQL por cada campo personalizado.</div>
                </section>

                <section class="wizard-step" data-step="3" hidden>
                    <h2>4. Revisión y confirmación</h2>
                    <div class="review-list" id="setup-review"></div>
                    <div class="note">Al confirmar, NEXORA creará el propietario, guardará la configuración, sembrará la plantilla y bloqueará este asistente. Esta acción no se puede repetir en la misma instalación.</div>
                </section>

                <div class="wizard-actions">
                    <button type="button" class="secondary-button" id="wizard-prev" hidden>Volver</button>
                    <button type="button" class="primary-button" id="wizard-next">Continuar</button>
                    <button type="submit" class="primary-button" id="wizard-submit" hidden>Crear instalación</button>
                </div>
            </form>
        @endif
    </section>
@endsection

@push('head')
<style>
    label{display:grid;gap:7px;margin:13px 0;font-size:13px;font-weight:650}
    input,select,textarea{width:100%;min-width:0;border:1px solid #dfe5d8;border-radius:10px;padding:11px 12px;background:#fff;color:#20271f;font:inherit}
    input:focus,select:focus,textarea:focus{outline:2px solid #c7d89b;outline-offset:1px}
    input[type=color]{height:44px;padding:4px}input[type=checkbox],input[type=radio]{width:16px;height:16px;accent-color:#6c8d28}
    .field-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.password-control{display:flex;align-items:center;gap:8px}.password-control input{flex:1;min-width:0}.password-toggle{flex:none;min-height:42px;padding:0 12px;border:1px solid #dfe5d8;border-radius:10px;background:#f4f6ef;color:#35402b;font:inherit;font-size:12px;font-weight:750;cursor:pointer}.password-toggle:focus-visible{outline:2px solid #89a92f;outline-offset:2px}.small{font-size:12px}.wizard-progress{margin:22px 0}.wizard-current{font-size:12px;font-weight:800}.wizard-track{height:5px;margin-top:9px;border-radius:10px;background:#e9eee2;overflow:hidden}.wizard-track span{display:block;width:25%;height:100%;background:#89a92f;transition:width .2s}
    .wizard-step h2{margin:8px 0 15px}.wizard-step[hidden]{display:none}.wizard-actions{display:flex;justify-content:space-between;gap:10px;margin-top:22px}
    .primary-button,.secondary-button{min-height:42px;padding:0 17px;border:0;border-radius:10px;font:inherit;font-weight:750;cursor:pointer}.primary-button{background:#20271f;color:#fff}.secondary-button{background:#eef1e9;color:#35402b}
    .currency-options{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px}.currency-options label,.toggle-row{display:flex;align-items:center;gap:8px;padding:8px;border:1px solid #e7ebdf;border-radius:9px}.currency-options input,.toggle-row input{width:16px;height:16px}
    .section-separator{margin-top:22px;padding-top:17px;border-top:1px solid #edf0e9}.section-heading{display:flex;align-items:center;justify-content:space-between;gap:10px}.section-heading h3{margin:0;font-size:15px}.section-heading p{margin:5px 0}.phone-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;align-items:center;padding:12px 0;border-bottom:1px solid #edf0e9}.phone-row label{margin:4px 0}.phone-options{display:flex;gap:12px;flex-wrap:wrap;font-size:12px}.phone-options label{display:flex;align-items:center;gap:6px}
    .template-summary,.review-list{padding:15px;border-radius:11px;background:#f4f6ef;font-size:13px;line-height:1.7}.template-summary{display:grid;gap:12px}.template-summary__section{display:grid;gap:7px;padding-top:10px;border-top:1px solid #e2e8d9}.template-summary__items{display:flex;flex-wrap:wrap;gap:7px}.template-summary__chip{padding:4px 9px;border:1px solid #e0e7d6;border-radius:999px;background:#fff;font-size:12px}.template-summary__fields{color:#4e5846}.review-list div{display:flex;justify-content:space-between;gap:12px;padding:6px 0;border-bottom:1px solid #e7ebdf}.review-list div:last-child{border:0}
    .section-heading .secondary-button{white-space:nowrap}
    @media(max-width:560px){.field-grid{grid-template-columns:1fr;gap:0}.currency-options{grid-template-columns:repeat(3,minmax(0,1fr))}.phone-row{grid-template-columns:1fr}.wizard-actions button{flex:1}.section-heading{align-items:flex-start;flex-direction:column}}
</style>
@endpush

@push('scripts')
<script>
(() => {
    const form = document.getElementById('nexora-setup-form');
    if (!form) return;
    const steps = [...form.querySelectorAll('[data-step]')];
    const prev = document.getElementById('wizard-prev');
    const next = document.getElementById('wizard-next');
    const submit = document.getElementById('wizard-submit');
    const counter = document.getElementById('wizard-current');
    const progress = document.getElementById('wizard-progress-bar');
    const phoneList = document.getElementById('phone-list');
    let current = 0;

    form.querySelectorAll('[data-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.getAttribute('aria-controls'));

            if (!input) return;

            const shouldShow = input.type === 'password';
            input.type = shouldShow ? 'text' : 'password';
            button.textContent = shouldShow ? 'Ocultar' : 'Mostrar';
            button.setAttribute('aria-label', shouldShow ? 'Ocultar contraseña' : 'Mostrar contraseña');
            button.setAttribute('aria-pressed', String(shouldShow));
        });
    });

    const showStep = (index) => {
        current = index;
        steps.forEach((step, i) => { step.hidden = i !== index; });
        prev.hidden = index === 0;
        next.hidden = index === steps.length - 1;
        submit.hidden = index !== steps.length - 1;
        counter.textContent = 'Paso ' + (index + 1) + ' de ' + steps.length;
        progress.style.width = (((index + 1) / steps.length) * 100) + '%';
        if (index === 2) renderTemplate();
        if (index === 3) renderReview();
    };

    const validateStep = () => {
        for (const field of steps[current].querySelectorAll('input,select,textarea')) {
            if (field.type === 'hidden' || field.disabled) continue;
            if (!field.checkValidity()) {
                field.reportValidity();
                return false;
            }
        }
        return true;
    };

    const reindexPhones = () => {
        [...phoneList.querySelectorAll('[data-phone-row]')].forEach((row, index) => {
            row.querySelector('[data-phone-number]').name = 'phones[' + index + '][number]';
            row.querySelector('[data-phone-show-hidden]').name = 'phones[' + index + '][show_public]';
            row.querySelector('[data-phone-show]').name = 'phones[' + index + '][show_public]';
            row.querySelector('[data-phone-primary]').value = String(index);
        });
    };

    document.getElementById('add-phone').addEventListener('click', () => {
        const rows = phoneList.querySelectorAll('[data-phone-row]');
        if (rows.length >= 5) return;
        const row = document.createElement('div');
        row.className = 'phone-row';
        row.dataset.phoneRow = '';

        const details = document.createElement('div');
        const phoneLabel = document.createElement('label');
        phoneLabel.append(document.createTextNode('Teléfono de contacto'));

        const phoneInput = document.createElement('input');
        phoneInput.dataset.phoneNumber = '';
        phoneInput.type = 'tel';
        phoneInput.inputMode = 'tel';
        phoneInput.maxLength = 20;
        phoneInput.placeholder = '+505 8888 8888';
        phoneInput.required = true;
        phoneLabel.append(phoneInput);

        const phoneOptions = document.createElement('div');
        phoneOptions.className = 'phone-options';

        const primaryLabel = document.createElement('label');
        const primaryInput = document.createElement('input');
        primaryInput.dataset.phonePrimary = '';
        primaryInput.type = 'radio';
        primaryInput.name = 'primary_phone';
        primaryInput.value = '0';
        primaryInput.checked = rows.length === 0;
        primaryLabel.append(primaryInput, document.createTextNode(' Principal'));

        const publicLabel = document.createElement('label');
        const hiddenVisibilityInput = document.createElement('input');
        hiddenVisibilityInput.dataset.phoneShowHidden = '';
        hiddenVisibilityInput.type = 'hidden';
        hiddenVisibilityInput.value = '0';

        const publicVisibilityInput = document.createElement('input');
        publicVisibilityInput.dataset.phoneShow = '';
        publicVisibilityInput.type = 'checkbox';
        publicVisibilityInput.value = '1';
        publicVisibilityInput.checked = true;
        publicLabel.append(
            hiddenVisibilityInput,
            publicVisibilityInput,
            document.createTextNode(' Mostrar públicamente'),
        );

        phoneOptions.append(primaryLabel, publicLabel);
        details.append(phoneLabel, phoneOptions);

        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'secondary-button';
        removeButton.dataset.removePhone = '';
        removeButton.textContent = 'Quitar';
        removeButton.addEventListener('click', () => {
            row.remove();
            reindexPhones();
        });

        row.append(details, removeButton);
        phoneList.append(row);
        reindexPhones();
    });

    const getValue = (name) => form.elements.namedItem(name)?.value || '';
    const templatePreviews = @json($templatePreviews);
    const renderTemplate = () => {
        const container = document.getElementById('template-summary');
        const preview = templatePreviews[getValue('business_type')];
        container.replaceChildren();

        if (!preview) {
            container.textContent = 'Selecciona un tipo de negocio en el paso 1 para ver su plantilla.';
            return;
        }

        const title = document.createElement('strong');
        title.textContent = 'Plantilla seleccionada: ' + preview.label;
        container.append(title);

        const categoriesSection = document.createElement('section');
        categoriesSection.className = 'template-summary__section';
        const categoriesTitle = document.createElement('strong');
        categoriesTitle.textContent = 'Categorías que se crearán';
        const categories = document.createElement('div');
        categories.className = 'template-summary__items';

        preview.categories.forEach((category) => {
            const chip = document.createElement('span');
            chip.className = 'template-summary__chip';
            chip.textContent = category;
            categories.append(chip);
        });

        categoriesSection.append(categoriesTitle, categories);
        container.append(categoriesSection);

        preview.groups.forEach((group) => {
            const fieldsSection = document.createElement('section');
            fieldsSection.className = 'template-summary__section';
            const groupTitle = document.createElement('strong');
            groupTitle.textContent = 'Campos iniciales · ' + group.name;
            const fields = document.createElement('p');
            fields.className = 'template-summary__fields';
            fields.textContent = group.fields.join(' · ') || 'Sin campos iniciales';
            fieldsSection.append(groupTitle, fields);
            container.append(fieldsSection);
        });
    };

    const renderReview = () => {
        const currencies = [getValue('primary_currency')].concat(
            [...form.querySelectorAll('[name="additional_currencies[]"]:checked')].map(x => x.value)
        );
        const phones = [...phoneList.querySelectorAll('[data-phone-number]')].map(x => x.value).filter(Boolean);
        const rows = [
            ['Negocio', getValue('business_name')],
            ['Plantilla', form.elements.business_type.selectedOptions[0]?.textContent || ''],
            ['Propietario', getValue('owner_name')],
            ['Correo', getValue('owner_email')],
            ['Moneda principal', getValue('primary_currency')],
            ['Monedas habilitadas', [...new Set(currencies)].join(', ')],
            ['Contactos', phones.length ? phones.join(', ') : 'Sin teléfonos'],
            ['Fotografías', form.querySelector('input[type="checkbox"][name="photos_enabled"]')?.checked ? 'Habilitadas' : 'Deshabilitadas']
        ];
        document.getElementById('setup-review').replaceChildren(...rows.map(([label, content]) => {
            const row = document.createElement('div');
            const strong = document.createElement('strong');
            const span = document.createElement('span');
            strong.textContent = label;
            span.textContent = content;
            row.append(strong, span);
            return row;
        }));
    };

    next.addEventListener('click', () => {
        if (validateStep()) showStep(Math.min(current + 1, steps.length - 1));
    });
    prev.addEventListener('click', () => showStep(Math.max(current - 1, 0)));
    form.addEventListener('submit', (event) => {
        if (!validateStep()) {
            event.preventDefault();
            return;
        }
        submit.disabled = true;
        submit.textContent = 'Creando instalación…';
    });
    showStep(0);
})();
</script>
@endpush
