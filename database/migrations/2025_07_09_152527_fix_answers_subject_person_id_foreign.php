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
            $table->dropForeign(['subject_person_id']);
            $table->foreign('subject_person_id')
                ->references('id')->on('people')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('answers', function (Blueprint $table) {
            $table->dropForeign(['subject_person_id']);
            $table->foreign('subject_person_id')
                ->references('id')->on('users')
                ->onDelete('cascade');
        });
    }

};
