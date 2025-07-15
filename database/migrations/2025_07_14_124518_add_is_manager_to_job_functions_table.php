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
        Schema::table('job_functions', function (Blueprint $table) {
            $table->boolean('is_manager')->default(false)->after('type');
        });
    }

    public function down()
    {
        Schema::table('job_functions', function (Blueprint $table) {
            $table->dropColumn('is_manager');
        });
    }

};
