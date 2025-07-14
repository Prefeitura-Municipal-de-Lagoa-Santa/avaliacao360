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
            $table->string('sala')->nullable()->after('job_function_id');
            $table->string('descricao_sala')->nullable()->after('sala');
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn('sala');
            $table->dropColumn('descricao_sala');
        });
    }

};
