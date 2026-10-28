<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('vehicles', 'featured')) {
            return;
        }

        Schema::table('vehicles', function (Blueprint $table) {
            $table->boolean('featured')->default(false)->after('show_on_website');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('vehicles', 'featured')) {
            return;
        }

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('featured');
        });
    }
};
