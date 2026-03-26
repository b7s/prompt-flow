<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prompt_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->text('user_prompt');
            $table->longText('ai_response');
            $table->text('cli_type');
            $table->string('session_id')->nullable();
            $table->boolean('is_continued')->default(false);
            $table->foreignId('continued_from_id')->nullable()->constrained('prompt_histories')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prompt_histories');
    }
};
