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

            // <<< CORREÇÃO APLICADA AQUI >>>
            // Definição explícita da chave estrangeira, como solicitado.

            // Chave estrangeira para a tabela 'evaluations'.
            // Assumindo que a chave primária 'id' em 'evaluations' é do tipo UUID.
            // Se for um BIGINT, altere a linha abaixo para ->unsignedBigInteger('evaluation_id');
            $table->unsignedBigInteger('evaluation_id'); 
            $table->foreign('evaluation_id')
                  ->references('id')
                  ->on('evaluations')
                  ->onDelete('cascade');

            // Chave estrangeira para a tabela 'users'
            $table->unsignedBigInteger('evaluator_user_id');
            $table->foreign('evaluator_user_id')
                  ->references('id')
                  ->on('users')
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