<?php

// Comando para criar:
// php artisan make:migration create_questions_table
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
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            // Chave estrangeira para a tabela 'forms'
            $table->foreignId('form_id')
                  ->constrained('forms')
                  ->onDelete('cascade'); // Se o formulário for deletado, as perguntas relacionadas também serão.
            $table->text('text_content'); // Conteúdo/texto da pergunta
            $table->unsignedTinyInteger('weight')->default(1); // Peso da pergunta
            $table->timestamps();
        });
    }

    /**
     * Reverte as migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
