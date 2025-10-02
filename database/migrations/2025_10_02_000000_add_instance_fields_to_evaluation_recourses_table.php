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
        Schema::table('evaluation_recourses', function (Blueprint $table) {
            // Instância atual do processo de recurso: RH ou Comissão
            $table->enum('current_instance', ['RH', 'Comissao'])->default('Comissao')->after('status');

            // Rastreamento de devolução
            // Nota: evitar FK em SQLite para não recriar a tabela; validação por aplicação
            $table->unsignedBigInteger('last_returned_by_user_id')->nullable()->after('current_instance');
            $table->enum('last_returned_to_instance', ['RH', 'Comissao'])->nullable()->after('last_returned_by_user_id');
            $table->timestamp('last_returned_at')->nullable()->after('last_returned_to_instance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('evaluation_recourses', function (Blueprint $table) {
            $table->dropColumn(['current_instance', 'last_returned_by_user_id', 'last_returned_to_instance', 'last_returned_at']);
        });
    }
};
