<?php

namespace App\Core\Installation;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class DatabaseInstaller
{
    public function prepare(InstallationState $installation): void
    {
        if ($installation->isCompleted()) {
            throw new RuntimeException('Esta instalación ya está completada.');
        }

        $connection = DB::connection();
        $driver = $connection->getDriverName();

        $supportedDriver = $driver === 'pgsql'
            || (app()->environment('testing') && $driver === 'sqlite');

        if ($supportedDriver === false) {
            throw new RuntimeException(
                'NEXORA requiere PostgreSQL para instalaciones reales. SQLite solo se admite en pruebas automatizadas.',
            );
        }

        $connection->getPdo();

        $schema = $connection->getSchemaBuilder();
        $tables = $schema->getTableListing();

        if ($schema->hasTable('migrations') === false && count($tables) > 0) {
            throw new RuntimeException(
                'La base de datos seleccionada no está vacía. Crea una base de datos exclusiva para NEXORA.',
            );
        }

        if ($schema->hasTable('migrations')) {
            $migrationFiles = glob(database_path('migrations/*.php'));
            $migrationFiles = $migrationFiles === false ? [] : $migrationFiles;

            $knownMigrations = collect($migrationFiles)
                ->map(static fn (string $path): string => pathinfo($path, PATHINFO_FILENAME))
                ->all();

            $unknownMigrations = DB::table('migrations')
                ->pluck('migration')
                ->reject(static fn (string $migration): bool => in_array($migration, $knownMigrations, true));

            if ($unknownMigrations->isNotEmpty()) {
                throw new RuntimeException(
                    'La base de datos contiene migraciones ajenas a NEXORA. Utiliza una base de datos exclusiva.',
                );
            }
        }

        $exitCode = Artisan::call('migrate', ['--force' => true]);

        if ($exitCode !== 0 || $installation->hasSchema() === false) {
            throw new RuntimeException(
                'No se pudo preparar toda la estructura de NEXORA. Revisa permisos del usuario de base de datos y vuelve a intentarlo.',
            );
        }
    }
}
