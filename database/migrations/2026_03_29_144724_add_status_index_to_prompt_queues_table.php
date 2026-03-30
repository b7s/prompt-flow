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
        // OPTIMIZATION: Add index on status alone for queries that don't filter by project_path
        Schema::table('prompt_queues', function (Blueprint $table) {
            $table->index('status', 'prompt_queues_status_index');

            // OPTIMIZATION: Add index on session_id for faster lookups
            $table->index('session_id', 'prompt_queues_session_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prompt_queues', function (Blueprint $table) {
            $table->dropIndex('prompt_queues_status_index');
            $table->dropIndex('prompt_queues_session_id_index');
        });
    }
};
