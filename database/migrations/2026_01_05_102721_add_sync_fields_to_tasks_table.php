<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('client_id')->nullable()->after('id'); // Unique ID from client for offline-created tasks
            $table->timestamp('server_updated_at')->nullable()->after('updated_at'); // When server last modified
            $table->boolean('is_deleted')->default(false)->after('priority'); // Soft delete for sync
            $table->bigInteger('version')->default(1)->after('is_deleted'); // Version number for conflict detection
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['client_id', 'server_updated_at', 'is_deleted', 'version']);
        });
    }
};
