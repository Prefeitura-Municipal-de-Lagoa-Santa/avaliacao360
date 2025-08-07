<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration

{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('evaluation_requests', function (Blueprint $table) {
            $table->dateTime('exception_date_first')->nullable();
            $table->dateTime('exception_date_end')->nullable();
            $table->unsignedBigInteger('released_by')->nullable();
            $table->foreign('released_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('evaluation_requests', function (Blueprint $table) {
            $table->dropForeign(['released_by']);
            $table->dropColumn(['exception_date_first', 'exception_date_end', 'released_by']);
        });
    }
};
