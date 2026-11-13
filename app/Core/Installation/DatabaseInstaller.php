<?php

namespace App\Core\Installation;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

        $supportedDriver = in_array($driver, ['mysql', 'mariadb'], true)
            || (app()->environment('testing') && $driver === 'sqlite');

        if ($supportedDriver === false) {
            throw new RuntimeException('NEXORA requiere MySQL o MariaDB para la instalación.');
        }

        $connection->getPdo();

        $schema = $connection->getSchemaBuilder();
        $tables = $schema->getTableListing();

        if (! $schema->hasTable('migrations') && $tables !== []) {
            throw new RuntimeException(
                'La base de datos seleccionada no está vacía. Crea una base de datos exclusiva para NEXORA.',
            );
        }

        if ($schema->hasTable('migrations')) {
            $knownMigrations = collect(glob(database_path('migrations/*.php')) ?: [])
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
