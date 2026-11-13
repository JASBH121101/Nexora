<?php

namespace App\Modules\Setup\Http\Controllers;

use App\Core\Installation\DatabaseInstaller;
use App\Core\Installation\InstallationState;
use App\Core\Installation\ServerRequirements;
use App\Models\BusinessContact;
use App\Models\BusinessCurrency;
use App\Models\InstallationSettings;
use App\Models\User;
use App\Modules\Setup\Services\TemplateCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

final class SetupController
{
    private const CURRENCIES = [
        'USD', 'NIO', 'EUR', 'GTQ', 'HNL', 'CRC', 'PAB', 'COP', 'MXN', 'CAD', 'GBP',
    ];

    public function __invoke(
        InstallationState $installation,
        ServerRequirements $requirements,
        TemplateCatalog $templates,
    ): View|RedirectResponse {
        if ($installation->isCompleted()) {
            return redirect()->route(Auth::check() ? 'admin.home' : 'admin.login');
        }

        $checks = $requirements->checks();

        return view('setup.index', [
            'checks' => $checks,
            'requirementsPassed' => collect($checks)->every(
                static fn (array $check): bool => $check['passed'],
            ),
            'schemaReady' => $installation->hasSchema(),
            'databaseConnected' => (bool) (collect($checks)->firstWhere('key', 'database')['passed'] ?? false),
            'templates' => $templates->all(),
            'templatePreviews' => $templates->previews(),
            'currencies' => self::CURRENCIES,
        ]);
    }

    public function prepareDatabase(
        InstallationState $installation,
        DatabaseInstaller $databaseInstaller,
    ): RedirectResponse {
        if ($installation->isCompleted()) {
            return redirect()->route('admin.login');
        }

        try {
            $databaseInstaller->prepare($installation);
        } catch (RuntimeException $exception) {
            return back()->withErrors([
                'database' => $exception->getMessage(),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'database' => 'No se pudo conectar o preparar la base de datos. Revisa DATABASE_URL o DB_URL y los permisos del usuario de PostgreSQL.',
            ]);
        }

        return redirect()->route('setup.show')->with(
            'status',
            'La estructura de la base de datos de NEXORA quedó preparada. Ya puedes continuar con el asistente.',
        );
    }

    public function store(
        Request $request,
        InstallationState $installation,
        ServerRequirements $requirements,
        TemplateCatalog $templates,
    ): RedirectResponse {
        if ($installation->isCompleted()) {
            return redirect()->route('admin.login');
        }

        $checks = $requirements->checks();

        if (
            ! $installation->hasSchema()
            || ! collect($checks)->every(
                static fn (array $check): bool => $check['passed'],
            )
        ) {
            throw ValidationException::withMessages([
                'setup' => 'El entorno todavía no cumple todos los requisitos de instalación.',
            ]);
        }

        $request->merge([
            'owner_username' => strtolower(trim((string) $request->input('owner_username'))),
            'owner_email' => strtolower(trim((string) $request->input('owner_email'))),
        ]);

        $phones = array_values(array_filter(
            (array) $request->input('phones', []),
            static fn ($phone): bool => is_array($phone)
                && trim((string) ($phone['number'] ?? '')) !== '',
        ));

        $request->merge(['phones' => $phones]);

        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:120'],
            'business_type' => ['required', 'string', Rule::in(array_keys($templates->all()))],
            'owner_name' => ['required', 'string', 'max:100'],
            'owner_username' => [
                'required',
                'string',
                'min:3',
                'max:32',
                'regex:/^[a-z0-9._-]+$/',
                'unique:users,username',
            ],
            'owner_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'owner_password' => ['required', 'string', 'min:8', 'confirmed'],
            'description' => ['nullable', 'string', 'max:700'],
            'primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'primary_currency' => ['required', 'string', Rule::in(self::CURRENCIES)],
            'additional_currencies' => ['sometimes', 'array', 'max:10'],
            'additional_currencies.*' => ['string', 'distinct', Rule::in(self::CURRENCIES)],
            'phones' => ['sometimes', 'array', 'max:5'],
            'phones.*.number' => ['required', 'string', 'max:20', 'regex:/^[0-9+()\\-\\s]{7,20}$/'],
            'phones.*.show_public' => ['nullable', 'boolean'],
            'primary_phone' => ['nullable', 'integer', 'min:0', 'max:4'],
            'photos_enabled' => ['sometimes', 'boolean'],
            'logo' => [
                'nullable',
                'prohibited_if:photos_enabled,0',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
                'dimensions:max_width=3000,max_height=3000',
            ],
            'cover' => [
                'nullable',
                'prohibited_if:photos_enabled,0',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
                'dimensions:max_width=5000,max_height=3000',
            ],
        ]);

        $enabledCurrencies = array_values(array_unique(array_merge(
            [$validated['primary_currency']],
            array_filter(
                $validated['additional_currencies'] ?? [],
                static fn (string $code): bool => $code !== $validated['primary_currency'],
            ),
        )));

        $photosEnabled = (bool) ($validated['photos_enabled'] ?? true);
        $storedFiles = [];

        try {
            $owner = DB::transaction(function () use (
                $request,
                $validated,
                $enabledCurrencies,
                $photosEnabled,
                $templates,
                &$storedFiles,
            ): User {
                if (User::query()->exists() || InstallationSettings::query()->exists()) {
                    throw ValidationException::withMessages([
                        'setup' => 'Esta instalación ya tiene un propietario o un proceso iniciado.',
                    ]);
                }

                $owner = User::query()->create([
                    'name' => $validated['owner_name'],
                    'username' => $validated['owner_username'],
                    'email' => $validated['owner_email'],
                    'password' => $validated['owner_password'],
                    'role' => 'owner',
                    'is_active' => true,
                    'must_change_password' => false,
                ]);

                $logoPath = null;
                $coverPath = null;

                if ($photosEnabled && $request->hasFile('logo')) {
                    $logoPath = $request->file('logo')->store('identity', 'public');
                    $storedFiles[] = $logoPath;
                }

                if ($photosEnabled && $request->hasFile('cover')) {
                    $coverPath = $request->file('cover')->store('identity', 'public');
                    $storedFiles[] = $coverPath;
                }

                $configuration = [
                    'identity' => [
                        'description' => $validated['description'] ?? '',
                        'primaryColor' => $validated['primary_color'],
                        'logoPath' => $logoPath,
                        'coverPath' => $coverPath,
                        'photosEnabled' => $photosEnabled,
                    ],
                    'pricing' => [
                        'primaryCurrency' => $validated['primary_currency'],
                        'enabledCurrencies' => $enabledCurrencies,
                    ],
                    'template' => $validated['business_type'],
                    'website' => [
                        'editorMode' => 'basic',
                        'draft' => true,
                    ],
                ];

                InstallationSettings::query()->create([
                    'id' => 1,
                    'business_name' => $validated['business_name'],
                    'business_type' => $validated['business_type'],
                    'owner_id' => $owner->id,
                    'configuration' => $configuration,
                    'setup_completed_at' => now(),
                ]);

                foreach ($enabledCurrencies as $index => $currency) {
                    BusinessCurrency::query()->create([
                        'code' => $currency,
                        'is_primary' => $currency === $validated['primary_currency'],
                        'is_enabled' => true,
                        'sort_order' => $index,
                    ]);
                }

                $phoneRows = array_values($validated['phones'] ?? []);
                $mainPhoneIndex = isset($validated['primary_phone'])
                    ? (int) $validated['primary_phone']
                    : 0;

                foreach ($phoneRows as $index => $phone) {
                    BusinessContact::query()->create([
                        'phone' => trim($phone['number']),
                        'is_primary' => $index === $mainPhoneIndex,
                        'show_public' => (bool) ($phone['show_public'] ?? false),
                        'sort_order' => $index,
                    ]);
                }

                $templates->seed($validated['business_type']);

                return $owner;
            });
        } catch (Throwable $exception) {
            foreach ($storedFiles as $path) {
                Storage::disk('public')->delete($path);
            }

            throw $exception;
        }

        Auth::login($owner);
        $request->session()->regenerate();

        $mediaMessage = $this->ensurePublicStorageLink();

        $verificationMessage = 'Configura SMTP para recibir el enlace de verificación del correo.';

        if (config('mail.default') === 'smtp' && filled(config('mail.mailers.smtp.host'))) {
            try {
                $owner->sendEmailVerificationNotification();
                $verificationMessage = 'Se envió un enlace de verificación al correo del propietario.';
            } catch (Throwable) {
                $verificationMessage = 'El catálogo se creó, pero falló el envío. Revisa SMTP y solicita un nuevo enlace desde el panel.';
            }
        }

        return redirect()->route('admin.home')
            ->with('status', 'NEXORA quedó configurado y la cuenta propietaria fue creada.')
            ->with('verification_message', $verificationMessage)
            ->with('media_message', $mediaMessage);
    }

    private function ensurePublicStorageLink(): ?string
    {
        if (app()->environment('testing')) {
            return null;
        }

        $link = public_path('storage');
        $target = storage_path('app/public');

        if (is_dir($target) === false) {
            if (mkdir($target, 0755, true) === false && is_dir($target) === false) {
                return 'No se pudo preparar el directorio de imágenes. Revisa los permisos de storage/app.';
            }
        }

        if (is_link($link)) {
            return realpath($link) === realpath($target)
                ? null
                : 'Ya existe un enlace public/storage hacia otra ubicación. NEXORA no lo modificó; revisa el enlace antes de publicar fotografías.';
        }

        if (file_exists($link)) {
            return 'Ya existe public/storage como archivo o carpeta. No se modificó para proteger tus datos; solicita revisar la ruta de almacenamiento.';
        }

        try {
            $exitCode = Artisan::call('storage:link');

            if (
                $exitCode === 0
                && is_link($link)
                && realpath($link) === realpath($target)
            ) {
                return null;
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        return 'El VPS no permitió crear el enlace de imágenes. Los archivos se guardan, pero para mostrarlos debe existir public/storage apuntando a storage/app/public.';
    }
}
