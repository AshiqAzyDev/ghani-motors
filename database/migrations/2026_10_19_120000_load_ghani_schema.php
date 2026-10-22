<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $sql = file_get_contents(database_path('schema.sql'));
        $sql = preg_replace('/CREATE DATABASE IF NOT EXISTS.*?;/s', '', $sql);
        $sql = preg_replace('/^USE\s+\w+\s*;/m', '', $sql);

        foreach (preg_split('/;\s*[\r\n]+/', $sql) as $statement) {
            $statement = trim($statement);
            $code = trim((string) preg_replace('/--.*$/m', '', $statement));
            if ($code === '') {
                continue;
            }
            DB::unprepared($statement);
        }
    }

    public function down(): void
    {
        $tables = [
            'activity_log', 'invoice_payments', 'invoice_lines', 'invoices', 'sales',
            'invoice_counters', 'bookings', 'valuation_photos', 'valuations', 'leads',
            'saved_vehicles', 'vehicle_charge_times', 'vehicle_charge_time_fetches',
            'vehicle_taxonomy_fetches', 'vehicle_price_history', 'vehicle_highlights',
            'vehicle_features', 'vehicle_images', 'vehicle_payloads', 'webhook_events',
            'sync_runs', 'vehicles', 'customers', 'api_blocks', 'api_call_log',
            'api_access_tokens', 'staff_users', 'dealers', 'integrations',
        ];

        DB::unprepared('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($tables as $table) {
            DB::unprepared('DROP TABLE IF EXISTS `'.$table.'`');
        }
        DB::unprepared('SET FOREIGN_KEY_CHECKS = 1');
    }
};
