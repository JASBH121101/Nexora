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
                    <span class="wizard-current" id="wizard-current">Paso 1 de 5</span>
                    <div class="wizard-track"><span id="wizard-progress-bar"></span></div>
                </div>

                <section class="wizard-step" data-step="0" aria-labelledby="regional-step-title">
                    <h2 id="regional-step-title">1. Idioma y país</h2>
                    <p class="muted">Primero establece el idioma de la instalación y el país donde opera el negocio. La sugerencia inicial se basa en el idioma preferido del navegador; puedes cambiarla.</p>
                    <div class="field-grid">
                        <label>Idioma principal
                            <select id="setup-locale" name="locale" required>
                                @foreach ($languages as $code => $label)
                                    <option value="{{ $code }}" @selected(old('locale', $defaultLocale) === $code)>{{ $label }} ({{ $code }})</option>
                                @endforeach
                            </select>
                        </label>
                        <label>País del negocio
                            <select id="country-code" name="country_code" required data-selected-country="{{ old('country_code', $defaultCountryCode) }}">
                                <option value="">Selecciona un país</option>
                            </select>
                        </label>
                    </div>
                    <p class="muted small">El país utiliza el código estándar ISO 3166-1 de dos letras. Esta selección no modifica la moneda principal, que se configura en el siguiente paso.</p>
                </section>

                <section class="wizard-step" data-step="1">
                    <h2>2. Negocio y propietario</h2>
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

                <section class="wizard-step" data-step="2" hidden>
                    <h2>3. Identidad y monedas</h2>
                    <label>Descripción del negocio
                        <textarea name="description" maxlength="700" rows="3" placeholder="Describe brevemente tu negocio">{{ old('description') }}</textarea>
                    </label>
                    <div class="field-grid">
                        <label>Color principal
                            <input name="primary_color" type="color" required value="{{ old('primary_color', '#89a92f') }}">
                        </label>
                        <label>Moneda principal
                            <select id="primary-currency" name="primary_currency" required data-selected-currency="{{ old('primary_currency', $defaultPrimaryCurrency) }}">
                                @foreach ($currencies as $currency)
                                    <option value="{{ $currency }}" @selected(old('primary_currency', $defaultPrimaryCurrency) === $currency)>{{ $currency }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    <p class="muted small">La moneda local se sugiere según el país seleccionado. Puedes cambiarla manualmente; las demás monedas se agregan una por una.</p>
                    <div class="currency-add-row">
                        <label for="additional-currency-picker">Añadir otra moneda
                            <select id="additional-currency-picker">
                                <option value="">Seleccionar moneda…</option>
                            </select>
                        </label>
                        <button type="button" class="secondary-button" id="add-currency">Añadir</button>
                    </div>
                    <div id="additional-currency-list" class="selected-currencies" aria-live="polite"></div>

                    <div class="section-separator">
                        <div class="section-heading">
                            <div><h3>Contactos del negocio</h3><p class="muted small">Hasta cinco teléfonos. Puedes ocultar un contacto del sitio público.</p></div>
                            <button type="button" class="secondary-button" id="add-phone">Añadir teléfono</button>
                        </div>
                        <div id="phone-list"></div>
                    </div>
                    <div class="section-separator">
                        <div class="section-heading">
                            <div><h3>Horarios de atención</h3><p class="muted small">Opcional. Añade los días y las horas en que atiende el negocio.</p></div>
                            <button type="button" class="secondary-button" id="add-business-hours">Añadir horario</button>
                        </div>
                        <div id="business-hours-list" class="business-hours-list"></div>
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

                <section class="wizard-step" data-step="3" hidden>
                    <h2>4. Plantilla y catálogo</h2>
                    <p class="muted">La plantilla cargará categorías y campos iniciales editables. Podrás cambiar sus etiquetas, agregar campos y reorganizar grupos desde el panel.</p>
                    <div class="template-summary" id="template-summary" aria-live="polite">Selecciona un tipo de negocio en el paso 2 para ver su plantilla.</div>
                    <div class="note">Los campos dinámicos se guardarán como definiciones y valores relacionados. NEXORA no creará columnas SQL por cada campo personalizado.</div>
                </section>

                <section class="wizard-step" data-step="4" hidden>
                    <h2>5. Revisión y confirmación</h2>
                    <div class="review-list" id="setup-review"></div>
                    <div class="note">Los datos de este asistente permanecen en el formulario y no se guardan como negocio ni como propietario hasta pulsar «Crear instalación». Esa acción sí completa la instalación y no se puede repetir en la misma base de datos.</div>
                </section>

            </form>
            <div class="wizard-cancel-row">
                <form id="nexora-cancel-form" method="POST" action="{{ route('setup.cancel') }}">
                    @csrf
                    <button type="submit" class="cancel-button" id="wizard-cancel">Cancelar prueba</button>
                </form>
                <span class="muted small">Cancelar no envía los datos del formulario ni crea registros.</span>
            </div>
            <div class="wizard-actions">
                <button type="button" class="secondary-button" id="wizard-prev" hidden>Volver</button>
                <button type="button" class="primary-button" id="wizard-next">Continuar</button>
                <button type="submit" class="primary-button" id="wizard-submit" form="nexora-setup-form" hidden>Crear instalación</button>
            </div>
        @endif
    </section>
@endsection

@push('head')
<style>
    label{display:grid;gap:7px;margin:13px 0;font-size:13px;font-weight:650}
    input,select,textarea{width:100%;min-width:0;border:1px solid #dfe5d8;border-radius:10px;padding:11px 12px;background:#fff;color:#20271f;font:inherit}
    input:focus,select:focus,textarea:focus{outline:2px solid #c7d89b;outline-offset:1px}
    input[type=color]{height:44px;padding:4px}input[type=checkbox],input[type=radio]{width:16px;height:16px;accent-color:#6c8d28}
    .field-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
    .currency-add-row{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:end;gap:9px;margin-top:9px}.currency-add-row label{margin:0}.currency-add-row button{min-height:41px}
    .selected-currencies{display:flex;flex-wrap:wrap;gap:7px;margin-top:9px}.currency-chip{display:inline-flex;align-items:center;gap:7px;padding:5px 8px;border:1px solid #e1e6da;border-radius:999px;background:#f7f8f4;font-size:12px}.currency-chip button{border:0;background:transparent;color:#7a453e;font:inherit;font-weight:800;cursor:pointer}
    .business-hours-list{display:grid;gap:7px;margin-top:8px}.business-hours-row{display:grid;grid-template-columns:minmax(100px,1.1fr) 1fr 1fr auto;align-items:end;gap:8px;padding:8px 9px;border:1px solid #e7ebdf;border-radius:9px}.business-hours-row label{margin:0;font-size:11px}.business-hours-row input,.business-hours-row select{padding:8px 9px;font-size:12px}.business-hours-remove{height:35px;padding:0 9px;border:0;border-radius:8px;background:#f8eeee;color:#8b3939;font:inherit;font-size:12px;font-weight:750;cursor:pointer}.empty-hours{margin:0;color:#7a8374;font-size:12px;padding:8px 0}.password-control{display:flex;align-items:center;gap:8px}.password-control input{flex:1;min-width:0}.password-toggle{flex:none;min-height:42px;padding:0 12px;border:1px solid #dfe5d8;border-radius:10px;background:#f4f6ef;color:#35402b;font:inherit;font-size:12px;font-weight:750;cursor:pointer}.password-toggle:focus-visible{outline:2px solid #89a92f;outline-offset:2px}.small{font-size:12px}.wizard-progress{margin:22px 0}.wizard-current{font-size:12px;font-weight:800}.wizard-track{height:5px;margin-top:9px;border-radius:10px;background:#e9eee2;overflow:hidden}.wizard-track span{display:block;width:25%;height:100%;background:#89a92f;transition:width .2s}
    .wizard-step h2{margin:8px 0 15px}.wizard-step[hidden]{display:none}.wizard-actions{display:flex;justify-content:space-between;gap:10px;margin-top:14px}.wizard-cancel-row{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:20px}.cancel-button{padding:7px 0;border:0;background:transparent;color:#7a453e;font:inherit;font-size:12px;font-weight:800;text-decoration:underline;text-underline-offset:3px;cursor:pointer}
    .primary-button,.secondary-button{min-height:42px;padding:0 17px;border:0;border-radius:10px;font:inherit;font-weight:750;cursor:pointer}.primary-button{background:#20271f;color:#fff}.secondary-button{background:#eef1e9;color:#35402b}
    .currency-options{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px}.currency-options label,.toggle-row{display:flex;align-items:center;gap:8px;padding:8px;border:1px solid #e7ebdf;border-radius:9px}.currency-options input,.toggle-row input{width:16px;height:16px}
    .section-separator{margin-top:22px;padding-top:17px;border-top:1px solid #edf0e9}.section-heading{display:flex;align-items:center;justify-content:space-between;gap:10px}.section-heading h3{margin:0;font-size:15px}.section-heading p{margin:5px 0}.phone-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;align-items:center;padding:12px 0;border-bottom:1px solid #edf0e9}.phone-row label{margin:4px 0}.phone-options{display:flex;gap:12px;flex-wrap:wrap;font-size:12px}.phone-options label{display:flex;align-items:center;gap:6px}
    .template-summary,.review-list{padding:15px;border-radius:11px;background:#f4f6ef;font-size:13px;line-height:1.7}.template-summary{display:grid;gap:12px}.template-summary__section{display:grid;gap:7px;padding-top:10px;border-top:1px solid #e2e8d9}.template-summary__items{display:flex;flex-wrap:wrap;gap:7px}.template-summary__chip{padding:4px 9px;border:1px solid #e0e7d6;border-radius:999px;background:#fff;font-size:12px}.template-summary__fields{color:#4e5846}.review-list div{display:flex;justify-content:space-between;gap:12px;padding:6px 0;border-bottom:1px solid #e7ebdf}.review-list div:last-child{border:0}
    .section-heading .secondary-button{white-space:nowrap}
    @media(max-width:560px){.field-grid{grid-template-columns:1fr;gap:0}.phone-row{grid-template-columns:1fr}.wizard-actions button{flex:1}.wizard-cancel-row{align-items:flex-start;flex-direction:column;gap:4px}.section-heading{align-items:flex-start;flex-direction:column}.currency-add-row{grid-template-columns:minmax(0,1fr) auto}.business-hours-row{grid-template-columns:1fr 1fr;align-items:center}.business-hours-row label:first-child{grid-column:1/-1}.business-hours-remove{grid-column:1/-1;width:100%}}
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
    const cancel = document.getElementById('wizard-cancel');
    const localeSelect = document.getElementById('setup-locale');
    const countrySelect = document.getElementById('country-code');
    const countryCodes = @json($countryCodes);
    const countryCurrencies = @json($countryCurrencies);
    const currencies = @json($currencies);
    const preferredCountry = countrySelect.dataset.selectedCountry || 'NI';
    const currencySelect = document.getElementById('primary-currency');
    const currencyPicker = document.getElementById('additional-currency-picker');
    const addCurrencyButton = document.getElementById('add-currency');
    const currencyList = document.getElementById('additional-currency-list');
    const hoursList = document.getElementById('business-hours-list');
    const initialAdditionalCurrencies = @json(old('additional_currencies', []));
    const initialBusinessHours = @json(old('business_hours', []));
    const selectedAdditionalCurrencies = [];
    let businessHours = [];
    let current = 0;

    const currencyLabel = (code) => {
        try {
            const names = new Intl.DisplayNames([localeSelect.value || 'es'], { type: 'currency' });
            const name = names.of(code);

            return name && name !== code ? code + ' — ' + name : code;
        } catch (error) {
            return code;
        }
    };

    const renderCurrencyOptions = () => {
        const selected = currencySelect.value || currencySelect.dataset.selectedCurrency || 'USD';
        currencySelect.replaceChildren(...currencies.map((code) => {
            const option = document.createElement('option');
            option.value = code;
            option.textContent = currencyLabel(code);
            return option;
        }));
        currencySelect.value = currencies.includes(selected) ? selected : 'USD';

        const available = currencies.filter((code) =>
            code !== currencySelect.value && !selectedAdditionalCurrencies.includes(code)
        );
        currencyPicker.replaceChildren();
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = 'Seleccionar moneda…';
        currencyPicker.append(placeholder);
        available.forEach((code) => {
            const option = document.createElement('option');
            option.value = code;
            option.textContent = currencyLabel(code);
            currencyPicker.append(option);
        });
        addCurrencyButton.disabled = available.length === 0;
    };

    const renderAdditionalCurrencies = () => {
        currencyList.replaceChildren();
        selectedAdditionalCurrencies.forEach((code) => {
            const chip = document.createElement('span');
            chip.className = 'currency-chip';
            const label = document.createElement('span');
            label.textContent = currencyLabel(code);
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.textContent = 'Quitar';
            remove.setAttribute('aria-label', 'Quitar moneda ' + code);
            remove.addEventListener('click', () => {
                const index = selectedAdditionalCurrencies.indexOf(code);
                if (index !== -1) selectedAdditionalCurrencies.splice(index, 1);
                renderAdditionalCurrencies();
            });
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'additional_currencies[]';
            hidden.value = code;
            chip.append(label, remove, hidden);
            currencyList.append(chip);
        });
        renderCurrencyOptions();
    };

    const applyCountryCurrency = () => {
        const suggested = countryCurrencies[countrySelect.value];
        if (suggested && currencies.includes(suggested)) {
            currencySelect.value = suggested;
            const index = selectedAdditionalCurrencies.indexOf(suggested);
            if (index !== -1) selectedAdditionalCurrencies.splice(index, 1);
        }
        renderAdditionalCurrencies();
    };

    const dayLabels = {
        es: { monday: 'Lunes', tuesday: 'Martes', wednesday: 'Miércoles', thursday: 'Jueves', friday: 'Viernes', saturday: 'Sábado', sunday: 'Domingo' },
        en: { monday: 'Monday', tuesday: 'Tuesday', wednesday: 'Wednesday', thursday: 'Thursday', friday: 'Friday', saturday: 'Saturday', sunday: 'Sunday' }
    };
    const weekDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    const renderBusinessHours = () => {
        hoursList.replaceChildren();
        if (businessHours.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'empty-hours';
            empty.textContent = 'No has añadido horarios. Puedes dejarlos vacíos.';
            hoursList.append(empty);
            return;
        }

        businessHours.forEach((hours, index) => {
            const row = document.createElement('div');
            row.className = 'business-hours-row';
            const addField = (labelText, input) => {
                const label = document.createElement('label');
                label.append(document.createTextNode(labelText), input);
                row.append(label);
            };
            const day = document.createElement('select');
            day.name = 'business_hours[' + index + '][day]';
            day.setAttribute('aria-label', 'Día de atención');
            weekDays.forEach((key) => {
                const option = document.createElement('option');
                option.value = key;
                option.textContent = (dayLabels[localeSelect.value] || dayLabels.es)[key];
                day.append(option);
            });
            day.value = hours.day || weekDays[index % weekDays.length];

            const opens = document.createElement('input');
            opens.type = 'time';
            opens.name = 'business_hours[' + index + '][open]';
            opens.required = true;
            opens.value = hours.open || '09:00';

            const closes = document.createElement('input');
            closes.type = 'time';
            closes.name = 'business_hours[' + index + '][close]';
            closes.required = true;
            closes.value = hours.close || '18:00';

            addField(localeSelect.value === 'en' ? 'Day' : 'Día', day);
            addField(localeSelect.value === 'en' ? 'Opens' : 'Apertura', opens);
            addField(localeSelect.value === 'en' ? 'Closes' : 'Cierre', closes);

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'business-hours-remove';
            remove.textContent = localeSelect.value === 'en' ? 'Remove' : 'Quitar';
            remove.addEventListener('click', () => {
                businessHours.splice(index, 1);
                renderBusinessHours();
            });
            row.append(remove);
            hoursList.append(row);
            day.addEventListener('change', () => { hours.day = day.value; });
            opens.addEventListener('change', () => { hours.open = opens.value; });
            closes.addEventListener('change', () => { hours.close = closes.value; });
        });
    };

    initialAdditionalCurrencies.forEach((code) => {
        if (currencies.includes(code) && code !== currencySelect.value && !selectedAdditionalCurrencies.includes(code)) {
            selectedAdditionalCurrencies.push(code);
        }
    });
    businessHours = Array.isArray(initialBusinessHours) ? initialBusinessHours.map((entry) => ({ ...entry })) : [];
    renderCurrencyOptions();
    renderAdditionalCurrencies();
    renderBusinessHours();

    const renderCountryOptions = () => {
        const locale = localeSelect.value || 'es';
        let displayNames = null;

        try {
            displayNames = typeof Intl !== 'undefined' && Intl.DisplayNames
                ? new Intl.DisplayNames([locale], { type: 'region' })
                : null;
        } catch (error) {
            displayNames = null;
        }

        const options = countryCodes.map((code) => ({
            code,
            label: displayNames?.of(code) || code,
        })).sort((a, b) => a.label.localeCompare(b.label, locale));

        const selected = countrySelect.value || preferredCountry;
        countrySelect.replaceChildren(...options.map(({ code, label }) => {
            const option = document.createElement('option');
            option.value = code;
            option.textContent = label + ' (' + code + ')';
            return option;
        }));

        countrySelect.value = countryCodes.includes(selected) ? selected : 'NI';
    };

    renderCountryOptions();
    localeSelect.addEventListener('change', () => {
        renderCountryOptions();
        renderCurrencyOptions();
        renderBusinessHours();
    });
    countrySelect.addEventListener('change', applyCountryCurrency);
    currencySelect.addEventListener('change', () => {
        const index = selectedAdditionalCurrencies.indexOf(currencySelect.value);
        if (index !== -1) selectedAdditionalCurrencies.splice(index, 1);
        renderAdditionalCurrencies();
    });
    addCurrencyButton.addEventListener('click', () => {
        const code = currencyPicker.value;
        if (!code || selectedAdditionalCurrencies.includes(code)) return;
        if (selectedAdditionalCurrencies.length >= 10) {
            window.alert('Puedes añadir hasta diez monedas adicionales.');
            return;
        }
        selectedAdditionalCurrencies.push(code);
        renderAdditionalCurrencies();
    });
    document.getElementById('add-business-hours').addEventListener('click', () => {
        if (businessHours.length >= 14) return;
        businessHours.push({ day: weekDays[businessHours.length % weekDays.length], open: '09:00', close: '18:00' });
        renderBusinessHours();
    });

    cancel.addEventListener('click', (event) => {
        if (!window.confirm('¿Cancelar esta prueba? No se creará el propietario, el negocio ni las categorías. La estructura vacía de tablas se conservará para repetir la prueba.')) {
            event.preventDefault();
        }
    });

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
        if (index === 3) renderTemplate();
        if (index === 4) renderReview();
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
            ['Idioma principal', localeSelect.selectedOptions[0]?.textContent || getValue('locale')],
            ['País del negocio', countrySelect.selectedOptions[0]?.textContent || getValue('country_code')],
            ['Negocio', getValue('business_name')],
            ['Plantilla', form.elements.business_type.selectedOptions[0]?.textContent || ''],
            ['Propietario', getValue('owner_name')],
            ['Correo', getValue('owner_email')],
            ['Moneda principal', getValue('primary_currency')],
            ['Monedas habilitadas', [...new Set(currencies)].join(', ')],
            ['Contactos', phones.length ? phones.join(', ') : 'Sin teléfonos'],
            ['Horarios', businessHours.length ? businessHours.map((item) => {
                const day = (dayLabels[localeSelect.value] || dayLabels.es)[item.day] || item.day;
                return day + ' ' + item.open + '–' + item.close;
            }).join(', ') : 'No definidos'],
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
