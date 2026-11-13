<?php

namespace App\Core\Installation;

use Illuminate\Support\Facades\DB;
use Throwable;

final class ServerRequirements
{
    public function checks(): array
    {
        return [
            $this->checkPhpVersion(),
            $this->checkRequiredExtensions(),
            $this->checkDatabaseConnection(),
            ...$this->checkWritableDirectories(),
        ];
    }

    private function checkPhpVersion(): array
    {
        $minimum = '8.3.0';
        $passed = version_compare(PHP_VERSION, $minimum, '>=');

        return [
            'key' => 'php',
            'label' => 'Versión de PHP',
            'passed' => $passed,
            'detail' => sprintf('Detectado %s; mínimo requerido: %s.', PHP_VERSION, $minimum),
        ];
    }

    private function checkRequiredExtensions(): array
    {
        $required = [
            'ctype', 'curl', 'dom', 'fileinfo', 'filter', 'hash',
            'mbstring', 'openssl', 'pcre', 'pdo', 'session',
            'tokenizer', 'xml', 'pdo_mysql',
        ];
        $missing = array_values(array_filter(
            $required,
            static fn (string $extension): bool => ! extension_loaded($extension),
        ));

        return [
            'key' => 'extensions',
            'label' => 'Extensiones PHP',
            'passed' => $missing === [],
            'detail' => $missing === []
                ? 'Todas las extensiones obligatorias están disponibles.'
                : 'Faltan: '.implode(', ', $missing).'.',
        ];
    }

    private function checkDatabaseConnection(): array
    {
        try {
            DB::connection()->getPdo();

            return [
                'key' => 'database',
                'label' => 'Conexión a la base de datos',
                'passed' => true,
                'detail' => 'Laravel pudo abrir una conexión con el motor configurado.',
            ];
        } catch (Throwable $exception) {
            return [
                'key' => 'database',
                'label' => 'Conexión a la base de datos',
                'passed' => false,
                'detail' => app()->environment('production')
                    ? 'No se pudo conectar. Revisa DB_HOST, DB_DATABASE, DB_USERNAME y DB_PASSWORD.'
                    : 'No se pudo conectar: '.$exception->getMessage(),
            ];
        }
    }

    private function checkWritableDirectories(): array
    {
        $directories = [
            'storage' => storage_path(),
            'storage/app' => storage_path('app'),
            'storage/framework/cache' => storage_path('framework/cache'),
            'storage/framework/sessions' => storage_path('framework/sessions'),
            'storage/framework/views' => storage_path('framework/views'),
            'storage/logs' => storage_path('logs'),
            'bootstrap/cache' => base_path('bootstrap/cache'),
        ];
        $unwritable = array_keys(array_filter(
            $directories,
            static fn (string $path): bool => ! is_dir($path) || ! is_writable($path),
        ));

        return [[
            'key' => 'permissions',
            'label' => 'Permisos de escritura',
            'passed' => $unwritable === [],
            'detail' => $unwritable === []
                ? 'Los directorios requeridos permiten escritura.'
                : 'Revisa permisos de: '.implode(', ', $unwritable).'.',
        ]];
    }
}
