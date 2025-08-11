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
        Schema::table('activity_logs', function (Blueprint $table) {
            // Drop the existing index before changing the column
            $table->dropIndex(['model_type', 'model_id']);
            
            // Change model_id from unsignedBigInteger to string to support UUIDs
            $table->string('model_id')->change();
            
            // Recreate the index
            $table->index(['model_type', 'model_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            // Drop the index before changing the column back
            $table->dropIndex(['model_type', 'model_id']);
            
            // Change back to unsignedBigInteger
            $table->unsignedBigInteger('model_id')->change();
            
            // Recreate the index
            $table->index(['model_type', 'model_id']);
        });
    }
};
