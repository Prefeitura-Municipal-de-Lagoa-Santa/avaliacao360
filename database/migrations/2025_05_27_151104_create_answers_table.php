<?php

// Comando para criar:
// php artisan make:migration create_answers_table
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
        Schema::create('answers', function (Blueprint $table) {
            $table->id();

            // Chave estrangeira para a tabela 'questions'
            $table->foreignId('question_id')
                  ->constrained('questions')
                  ->onDelete('cascade');

            // Chave estrangeira para a tabela 'evaluations'
            $table->foreignId('evaluation_id')
                  ->constrained('evaluations')
                  ->onDelete('cascade');

            // Conteúdo da resposta
            $table->text('response_content')->nullable();

            // Chave estrangeira para a tabela 'users' (usuário que é o "sujeito" desta resposta específica)
            // Mantendo conforme o diagrama original que indicava uma coluna 'Avaliado_id' em 'Respostas'.
            // Se esta informação for sempre a mesma que 'evaluated_user_id' da tabela 'evaluations',
            // esta coluna pode ser redundante. Avalie a necessidade no seu contexto.
            $table->foreignId('subject_user_id')
                  ->constrained('users')
                  ->onDelete('cascade');

            $table->timestamps();

            // Opcional: Adicionar um índice único para evitar respostas duplicadas
            // para a mesma pergunta na mesma avaliação por parte do mesmo usuário (se aplicável ao seu caso de uso).
            // $table->unique(['question_id', 'evaluation_id', 'subject_user_id'], 'unique_answer_per_subject');
        });
    }

    /**
     * Reverte as migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('answers');
    }
};
