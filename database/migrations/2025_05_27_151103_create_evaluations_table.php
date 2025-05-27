<?php

// Comando para criar:
// php artisan make:migration create_evaluations_table
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
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->string('type', 45); // Tipo da avaliação

            // Chave estrangeira para a tabela 'forms'
            $table->foreignId('form_id')
                  ->constrained('forms')
                  ->onDelete('cascade');

            // Chave estrangeira para a tabela 'users' (usuário que está sendo avaliado)
            $table->foreignId('evaluated_user_id')
                  ->constrained('users')
                  ->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverte as migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};
