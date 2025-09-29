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
            $table->unsignedBigInteger('invalidated_by')->nullable()->after('updated_at');
            $table->timestamp('invalidated_at')->nullable()->after('invalidated_by');
            
            // Add foreign key constraint for invalidated_by
            $table->foreign('invalidated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('evaluation_requests', function (Blueprint $table) {
            $table->dropForeign(['invalidated_by']);
            $table->dropColumn(['invalidated_by', 'invalidated_at']);
        });
    }
};