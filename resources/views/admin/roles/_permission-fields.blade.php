@foreach ($permissionModules as $moduleKey => $module)
    <section class="permission-module">
        <h4>{{ $module['label'] }}</h4>
        <div class="permission-actions">
            @foreach ($module['permissions'] as $action => $label)
                @php($permissionKey = $moduleKey.'.'.$action)
                <label>
                    <input type="checkbox" name="permissions[]" value="{{ $permissionKey }}" @checked(in_array($permissionKey, $permissions, true))>
                    <span>{{ $label }}</span>
                </label>
            @endforeach
        </div>
    </section>
@endforeach

<section class="permission-module">
    <h4>Restricciones por campo de producto</h4>
    <p class="muted small">Heredar utiliza el permiso del módulo. Denegar bloquea la lectura o edición de ese campo. Para editar un campo también debe estar permitida su lectura.</p>
    <div class="field-rules">
        @foreach ($productFieldDefinitions as $fieldKey => $fieldLabel)
            <div class="field-rule">
                <strong>{{ $fieldLabel }}</strong>
                @foreach (['view' => 'Lectura', 'edit' => 'Edición'] as $action => $actionLabel)
                    <label>
                        <span class="sr-only">{{ $actionLabel }} · {{ $fieldLabel }}</span>
                        <select name="field_permissions[products][{{ $fieldKey }}][{{ $action }}]">
                            <option value="">Heredar</option>
                            <option value="allow" @selected(data_get($fieldPermissions, 'products.'.$fieldKey.'.'.$action) === 'allow')>Permitir</option>
                            <option value="deny" @selected(data_get($fieldPermissions, 'products.'.$fieldKey.'.'.$action) === 'deny')>Denegar</option>
                        </select>
                    </label>
                @endforeach
            </div>
        @endforeach
    </div>
</section>

<section class="permission-module">
    <h4>Restricciones por campo de inventario</h4>
    <p class="muted small">Estas reglas se aplicarán a las cantidades y disponibilidad cuando se active Inventario básico.</p>
    <div class="field-rules">
        @foreach ($inventoryFieldDefinitions as $fieldKey => $fieldLabel)
            <div class="field-rule">
                <strong>{{ $fieldLabel }}</strong>
                @foreach (['view' => 'Lectura', 'edit' => 'Edición'] as $action => $actionLabel)
                    <label>
                        <span class="sr-only">{{ $actionLabel }} · {{ $fieldLabel }}</span>
                        <select name="field_permissions[inventory][{{ $fieldKey }}][{{ $action }}]">
                            <option value="">Heredar</option>
                            <option value="allow" @selected(data_get($fieldPermissions, 'inventory.'.$fieldKey.'.'.$action) === 'allow')>Permitir</option>
                            <option value="deny" @selected(data_get($fieldPermissions, 'inventory.'.$fieldKey.'.'.$action) === 'deny')>Denegar</option>
                        </select>
                    </label>
                @endforeach
            </div>
        @endforeach
    </div>
</section>
