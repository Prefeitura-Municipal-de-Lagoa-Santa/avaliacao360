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
        Schema::table('job_functions', function ($table) {
            $table->string('code')->nullable()->after('id');
        });
    }

    public function down()
    {
        Schema::table('job_functions', function ($table) {
            $table->dropColumn('code');
        });
    }

};
