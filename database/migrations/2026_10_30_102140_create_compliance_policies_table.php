<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Table may already exist when loaded from schema.sql on fresh installs.
        if (Schema::hasTable('compliance_policies')) {
            return;
        }

        Schema::create('compliance_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dealer_id')->constrained('dealers')->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('pdf_path');
            $table->string('original_filename');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['dealer_id', 'sort_order', 'name'], 'compliance_policies_dealer_sort_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_policies');
    }
};
