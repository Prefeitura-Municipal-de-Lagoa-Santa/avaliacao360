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
        Schema::create('organizational_units', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Nome da unidade (ex: "Secretaria de Educação")
            $table->string('type'); // Tipo da unidade (ex: 'Secretaria', 'Diretoria', 'Coordenação', 'Departamento')
            $table->string('code')->nullable()->unique(); // Código da Lotação (ex: 1040101)
            $table->text('description')->nullable();
            
            // Chave estrangeira para o pai na mesma tabela, permitindo hierarquia
            $table->foreignId('parent_id')
                  ->nullable()
                  ->constrained('organizational_units')
                  ->onDelete('cascade'); // Se o pai for deletado, os filhos também são.

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizational_units');
    }
};