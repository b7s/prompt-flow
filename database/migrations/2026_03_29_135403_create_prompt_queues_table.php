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
        Schema::create('prompt_queues', function (Blueprint $table) {
            $table->id();
            $table->uuid('project_id')->nullable();
            $table->string('project_path')->nullable();
            $table->string('session_id')->nullable();
            $table->text('prompt');
            $table->string('status')->default('pending');
            $table->integer('position')->default(0);
            $table->string('chat_id')->nullable();
            $table->string('channel')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['project_path', 'status', 'position']);
            $table->index(['chat_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prompt_queues');
    }
};
