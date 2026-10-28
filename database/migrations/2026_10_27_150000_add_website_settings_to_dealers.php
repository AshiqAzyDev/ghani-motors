<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dealers', function (Blueprint $table) {
            $table->string('email', 255)->nullable()->after('phone');
            $table->string('tagline', 160)->nullable()->after('trading_name');
            $table->string('logo_path', 255)->nullable()->after('website_host');
            $table->string('favicon_path', 255)->nullable()->after('logo_path');
            $table->string('opening_hours', 255)->nullable()->after('favicon_path');
        });
    }

    public function down(): void
    {
        Schema::table('dealers', function (Blueprint $table) {
            $table->dropColumn(['email', 'tagline', 'logo_path', 'favicon_path', 'opening_hours']);
        });
    }
};
