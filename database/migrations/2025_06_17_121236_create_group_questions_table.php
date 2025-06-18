<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')
                  ->constrained('forms')
                  ->onDelete('cascade');
            $table->string('name', 150);
            // CORRIGIDO: Alterado para 'decimal' para permitir casas decimais.
            // Permite valores como 50.00 ou 33.33
            $table->decimal('weight', 5, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_questions');
    }
};

