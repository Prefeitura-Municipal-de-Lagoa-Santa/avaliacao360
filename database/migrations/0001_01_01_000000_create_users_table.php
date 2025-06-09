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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('username');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');

            // --- Additional Fields Based on CSV (English variable names) ---

            $table->string('registration_number')->unique()->nullable(); // MATRICULA
            $table->string('bond_type')->nullable(); // VINCULO
            $table->string('functional_status')->nullable(); // SITUACAO_FUNCIONAL (originalmente SITUACAO)
            $table->string('cpf', 11)->unique()->nullable(); // CPF
            $table->string('rg_number')->nullable(); // RG_NUMERO
            $table->date('admission_date')->nullable(); // DATA_ADMISSAO (originalmente ADMISSAO)
            $table->date('dismissal_date')->nullable(); // DATA_DEMISSAO (originalmente DEMISSAO)
            $table->string('current_position')->nullable(); // CARGO_ATUAL (originalmente CARGO)
            $table->string('current_function')->nullable(); // FUNCAO_ATUAL (originalmente FUNCAO)
            $table->string('allocation_code')->nullable(); // LOTACAO_CODIGO (originalmente LOTACAO)
            $table->string('allocation_name')->nullable(); // LOTACAO_NOME (originalmente NOME_LOT)

            $table->rememberToken();
            $table->timestamps();

        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
