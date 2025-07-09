<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Executa a migration.
     */
    public function up(): void
    {
        Schema::table('evaluation_requests', function (Blueprint $table) {
            $table->text('evidencias')->nullable()->after('alguma_coluna_existente');
            // Substitua 'alguma_coluna_existente' pelo nome de uma coluna que já exista para ordenar a posição, 
            // ou remova o after se não se importar com a ordem.
        });
    }

    /**
     * Reverte a migration.
     */
    public function down(): void
    {
        Schema::table('evaluation_requests', function (Blueprint $table) {
            $table->dropColumn('evidencias');
        });
    }
};
