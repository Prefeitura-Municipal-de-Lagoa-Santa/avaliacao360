<?php

// Comando para criar:
// php artisan make:migration create_forms_table
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Executa as migrations.
     */
    public function up(): void
    {
        Schema::create('forms', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100); // Nome do formulário
            $table->year('year');        // Ano do formulário
            $table->string('type', 45);  // Tipo do formulário
            $table->timestamps();
        });
    }

    /**
     * Reverte as migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('forms');
    }
};
