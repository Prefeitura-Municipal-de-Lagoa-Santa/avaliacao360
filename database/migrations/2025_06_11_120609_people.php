<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table) {
            // Colunas Padrão do Laravel
            $table->id();
            $table->string('name');
            $table->string('registration_number')->unique()->nullable(); // MATRICULA
            $table->string('bond_type')->nullable(); // VINCULO
            $table->string('functional_status')->nullable(); // SITUACAO
            $table->string('cpf', 14)->nullable(); // CPF (aumentado para aceitar máscara se necessário)
            $table->string('rg_number')->nullable(); // RG_NUMERO
            $table->date('admission_date')->nullable(); // DATA_ADMISSAO
            $table->date('dismissal_date')->nullable(); // DATA_DEMISSAO
            $table->string('current_position')->nullable(); // CARGO_ATUAL
            $table->string('current_function')->nullable(); // FUNCAO_ATUAL

            $table->foreignId('organizational_unit_id')
                ->nullable() // Permitir que uma pessoa não tenha unidade atribuída inicialmente
                ->constrained('organizational_units')
                ->onDelete('set null'); // Se uma unidade for deletada, o campo fica nulo, não a pessoa.
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('persons');
    }
};
