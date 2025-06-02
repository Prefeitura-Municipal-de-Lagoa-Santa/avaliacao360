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
       $table->dropColumn('allocation_code'); 
       $table->dropColumn('allocation_name'); 
        
    });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $table->string('allocation_code')->nullable(); // LOTACAO_CODIGO (originalmente LOTACAO)
        $table->string('allocation_name')->nullable(); // LOTACAO_NOME (originalmente NOME_LOT)
    }
};
