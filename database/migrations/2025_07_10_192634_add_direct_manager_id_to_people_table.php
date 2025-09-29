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
            $table->unsignedBigInteger('direct_manager_id')->nullable()->after('id');
            $table->foreign('direct_manager_id')->references('id')->on('people')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropForeign(['direct_manager_id']);
            $table->dropColumn('direct_manager_id');
        });
    }

};
