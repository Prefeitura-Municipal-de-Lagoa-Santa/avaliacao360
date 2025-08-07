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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('model_type'); // Tipo do modelo (App\Models\User, etc)
            $table->unsignedBigInteger('model_id'); // ID do registro
            $table->string('action'); // created, updated, deleted
            $table->unsignedBigInteger('user_id')->nullable(); // ID do usuário que fez a ação
            $table->string('user_name')->nullable(); // Nome do usuário
            $table->ipAddress('ip_address')->nullable(); // IP do usuário
            $table->string('user_agent')->nullable(); // User agent do navegador
            $table->json('old_values')->nullable(); // Valores antigos (para updates e deletes)
            $table->json('new_values')->nullable(); // Valores novos (para creates e updates)
            $table->json('changes')->nullable(); // Apenas os campos que mudaram
            $table->text('description')->nullable(); // Descrição da ação
            $table->timestamps();
            
            // Índices para melhor performance
            $table->index(['model_type', 'model_id']);
            $table->index('user_id');
            $table->index('action');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
