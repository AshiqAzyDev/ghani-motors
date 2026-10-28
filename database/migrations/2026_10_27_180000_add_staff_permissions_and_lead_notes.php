<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_users', function (Blueprint $table) {
            $table->json('permissions')->nullable()->after('role');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->text('contact_notes')->nullable()->after('message');
            $table->text('additional_notes')->nullable()->after('contact_notes');
            $table->json('requirements')->nullable()->after('additional_notes');
            $table->timestamp('contacted_at')->nullable()->after('status');
        });

        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('notifiable_type', 40);
            $table->unsignedBigInteger('notifiable_id');
            $table->unsignedBigInteger('dealer_id')->nullable()->index();
            $table->string('type', 80);
            $table->string('title', 180);
            $table->string('body', 500)->nullable();
            $table->string('url', 500)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'app_notifications_reader_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['contact_notes', 'additional_notes', 'requirements', 'contacted_at']);
        });
        Schema::table('staff_users', function (Blueprint $table) {
            $table->dropColumn('permissions');
        });
    }
};
