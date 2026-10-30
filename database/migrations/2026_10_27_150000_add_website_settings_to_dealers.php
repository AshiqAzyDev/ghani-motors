<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // These columns may already exist when dealers is created from schema.sql.
        $columns = [
            'email' => fn (Blueprint $table) => $table->string('email', 255)->nullable()->after('phone'),
            'tagline' => fn (Blueprint $table) => $table->string('tagline', 160)->nullable()->after('trading_name'),
            'logo_path' => fn (Blueprint $table) => $table->string('logo_path', 255)->nullable()->after('website_host'),
            'favicon_path' => fn (Blueprint $table) => $table->string('favicon_path', 255)->nullable()->after('logo_path'),
            'opening_hours' => fn (Blueprint $table) => $table->string('opening_hours', 255)->nullable()->after('favicon_path'),
        ];

        $missing = collect($columns)
            ->reject(fn ($definition, string $column) => Schema::hasColumn('dealers', $column));

        if ($missing->isEmpty()) {
            return;
        }

        Schema::table('dealers', function (Blueprint $table) use ($missing) {
            foreach ($missing as $definition) {
                $definition($table);
            }
        });
    }

    public function down(): void
    {
        $columns = collect(['email', 'tagline', 'logo_path', 'favicon_path', 'opening_hours'])
            ->filter(fn (string $column) => Schema::hasColumn('dealers', $column))
            ->values()
            ->all();

        if ($columns === []) {
            return;
        }

        Schema::table('dealers', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }
};
