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
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->string('type', 45);

            $table->foreignId('form_id')
                  ->constrained('forms')
                  ->onDelete('cascade');

            // CORRIGIDO: Agora usa person_id e aponta para a tabela 'people'
            $table->foreignId('evaluated_person_id')
                  ->constrained('people')
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