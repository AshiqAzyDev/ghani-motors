<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class DatabaseMigrationService
{
    /**
     * @return array{
     *     connected: bool,
     *     state: 'up_to_date'|'pending'|'error',
     *     message: string,
     *     database: string,
     *     applied_count: int,
     *     expected_count: int,
     *     pending: list<string>
     * }
     */
    public function status(): array
    {
        $database = $this->databaseName();

        try {
            DB::connection()->getPdo();
        } catch (Throwable $exception) {
            report($exception);

            return [
                'connected' => false,
                'state' => 'error',
                'message' => 'Database connection failed. Check the database settings before running migrations.',
                'database' => $database,
                'applied_count' => 0,
                'expected_count' => 0,
                'pending' => [],
            ];
        }

        $expected = $this->expectedMigrationNames();

        if (! Schema::hasTable('migrations')) {
            return [
                'connected' => true,
                'state' => 'pending',
                'message' => 'Migrations table not found. All migrations are pending.',
                'database' => $database,
                'applied_count' => 0,
                'expected_count' => count($expected),
                'pending' => $expected,
            ];
        }

        $applied = $this->appliedMigrationNames();
        $pending = array_values(array_diff($expected, $applied));

        return [
            'connected' => true,
            'state' => $pending === [] ? 'up_to_date' : 'pending',
            'message' => $pending === []
                ? 'Database schema is up to date.'
                : count($pending).' migration(s) pending.',
            'database' => $database,
            'applied_count' => count($applied),
            'expected_count' => count($expected),
            'pending' => $pending,
        ];
    }

    /**
     * Apply only migrations that have not run yet. Existing tables and rows stay in place.
     *
     * @return array{ran: bool, output: string, status: array<string, mixed>}
     */
    public function runPendingMigrations(): array
    {
        $before = $this->status();

        if (! ($before['connected'] ?? false)) {
            throw new RuntimeException($before['message'] ?? 'Database is not connected.');
        }

        if (($before['state'] ?? '') !== 'pending') {
            return [
                'ran' => false,
                'output' => 'No pending migrations.',
                'status' => $before,
            ];
        }

        $exitCode = Artisan::call('migrate', [
            '--force' => true,
        ]);

        $output = trim(Artisan::output());

        if ($exitCode !== 0) {
            throw new RuntimeException($output !== '' ? $output : 'Migration failed.');
        }

        return [
            'ran' => true,
            'output' => $output,
            'status' => $this->status(),
        ];
    }

    private function databaseName(): string
    {
        $connection = (string) config('database.default');

        return (string) config("database.connections.{$connection}.database");
    }

    /**
     * @return list<string>
     */
    private function expectedMigrationNames(): array
    {
        $files = glob(database_path('migrations').DIRECTORY_SEPARATOR.'*.php') ?: [];
        $names = [];

        foreach ($files as $file) {
            $names[] = pathinfo($file, PATHINFO_FILENAME);
        }

        sort($names);

        return array_values(array_unique($names));
    }

    /**
     * @return list<string>
     */
    private function appliedMigrationNames(): array
    {
        return DB::table('migrations')
            ->orderBy('migration')
            ->pluck('migration')
            ->all();
    }
}
