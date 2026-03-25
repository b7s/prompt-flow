<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', static function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('path');
            $table->enum('status', ['active', 'inactive', 'archived'])->default('active');
            $table->string('cli_preference')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('path');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
