<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Executa a alteração da coluna.
     */
    public function up(): void
    {
        Schema::table('answers', function (Blueprint $table) {
            // Primeiro, dropa a foreign key antiga (é obrigatório no MySQL/MariaDB)
            $table->dropForeign(['subject_user_id']);
            // Renomeia a coluna
            $table->renameColumn('subject_user_id', 'subject_person_id');
        });

        // Agora recria a foreign key apontando para users (ou para a tabela correta)
        Schema::table('answers', function (Blueprint $table) {
            $table->foreign('subject_person_id')
                  ->references('id')->on('users')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverte a alteração da coluna.
     */
    public function down(): void
    {
        Schema::table('answers', function (Blueprint $table) {
            $table->dropForeign(['subject_person_id']);
            $table->renameColumn('subject_person_id', 'subject_user_id');
        });

        Schema::table('answers', function (Blueprint $table) {
            $table->foreign('subject_user_id')
                  ->references('id')->on('users')
                  ->onDelete('cascade');
        });
    }
};
