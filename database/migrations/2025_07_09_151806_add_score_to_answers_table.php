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
        Schema::table('answers', function (Blueprint $table) {
            $table->integer('score')->after('question_id');
        });
    }

    public function down()
    {
        Schema::table('answers', function (Blueprint $table) {
            $table->dropColumn('score');
        });
    }

};
