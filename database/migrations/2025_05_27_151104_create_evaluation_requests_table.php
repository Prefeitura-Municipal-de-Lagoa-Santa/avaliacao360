<?php

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
            $table->foreignId('evaluation_id')->constrained('evaluations')->onDelete('cascade');

            // CORRIGIDO: Usa requester_person_id e aponta para a tabela 'people'
            $table->foreignId('requester_person_id')->constrained('people')->onDelete('cascade');
            
            // CORRIGIDO: Usa requested_person_id e aponta para a tabela 'people'
            $table->foreignId('requested_person_id')->constrained('people')->onDelete('cascade');
            
            $table->string('status')->default('pending');
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