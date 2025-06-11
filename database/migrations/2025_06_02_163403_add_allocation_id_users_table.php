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
       Schema::table('users', function (Blueprint $table) {           
            $table->foreignId('allocation_id')
                  ->nullable()
                  ->constrained('allocations')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
        $table->dropForeign(['allocation_id']);
        $table->dropColumn('allocation_id');
     });
    }
};
