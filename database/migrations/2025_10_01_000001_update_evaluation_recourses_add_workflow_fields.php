<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('evaluation_recourses', function (Blueprint $table) {
            // Etapa atual do fluxo
            $table->enum('stage', ['rh', 'comissao', 'diretoria_rh', 'requerente', 'secretario', 'finalizado'])
                ->default('comissao')
                ->after('status');

            // Decisão e parecer da Comissão
            $table->enum('commission_decision', ['deferido', 'indeferido'])->nullable()->after('stage');
            $table->text('commission_response')->nullable()->after('commission_decision');
            $table->timestamp('commission_decided_at')->nullable()->after('commission_response');

            // Decisão e parecer da Diretoria (Diretora do RH)
            $table->enum('director_decision', ['deferido', 'indeferido'])->nullable()->after('commission_decided_at');
            $table->text('director_response')->nullable()->after('director_decision');
            $table->timestamp('director_decided_at')->nullable()->after('director_response');

            // Decisão e parecer do Secretário (2ª instância)
            $table->enum('secretary_decision', ['deferido', 'indeferido'])->nullable()->after('director_decided_at');
            $table->text('secretary_response')->nullable()->after('secretary_decision');
            $table->timestamp('secretary_decided_at')->nullable()->after('secretary_response');

            // Ciências do requerente (1ª e decisão final)
            $table->timestamp('ack_first_at')->nullable()->after('secretary_decided_at');
            $table->timestamp('ack_final_at')->nullable()->after('ack_first_at');
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_recourses', function (Blueprint $table) {
            $table->dropColumn([
                'stage',
                'commission_decision', 'commission_response', 'commission_decided_at',
                'director_decision', 'director_response', 'director_decided_at',
                'secretary_decision', 'secretary_response', 'secretary_decided_at',
                'ack_first_at', 'ack_final_at',
            ]);
        });
    }
};
