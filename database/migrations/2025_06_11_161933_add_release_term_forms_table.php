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
        Schema::table('forms', function (Blueprint $table) {           
            $table->timestamp('term')->nullable();
            $table->boolean('release')->nullable();
            $table->timestamp('release_data')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
      Schema::table('forms', function (Blueprint $table) {
        $table->dropColumn('term');
        $table->dropColumn('release');
        $table->dropColumn('release_data');
     });
    }
};
