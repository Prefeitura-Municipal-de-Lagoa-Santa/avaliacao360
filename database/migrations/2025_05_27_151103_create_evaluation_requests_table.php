<?php

// Comando para criar:
// php artisan make:migration create_evaluation_requests_table
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
        Schema::create('evaluation_requests', function (Blueprint $table) {
            $table->id();
            $table->string('type', 45); // Tipo do pedido de avaliação

            // Chave estrangeira para a tabela 'evaluations'
            $table->foreignId('evaluation_id')
                  ->constrained('evaluations')
                  ->onDelete('cascade');

            // Chave estrangeira para a tabela 'users' (usuário que vai realizar a avaliação)
            $table->foreignId('evaluator_user_id')
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
        Schema::dropIfExists('evaluation_requests');
    }
};
