<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('people', function (Blueprint $table) {
            // Primeiro, remova o campo antigo
            $table->dropColumn('current_function');
            // Depois, adicione a nova foreign key
            $table->foreignId('job_function_id')
                ->nullable()
                ->after('current_position')
                ->constrained('job_functions')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('people', function (Blueprint $table) {
            // Remove a foreign key e o campo novo
            $table->dropForeign(['job_function_id']);
            $table->dropColumn('job_function_id');
            // Restaura o campo antigo, se precisar voltar atrás
            $table->string('current_function')->nullable()->after('current_position');
        });
    }

};
