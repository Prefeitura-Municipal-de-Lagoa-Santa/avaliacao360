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
        Schema::table('pdi_requests', function (Blueprint $table) {
         $table->date('exception_date_first')->nullable()->after('status');
        $table->date('exception_date_end')->nullable()->after('exception_date_first');
        $table->foreignId('released_by')->nullable()->constrained('users')->onDelete('set null')->after('exception_date_end');
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pdi_requests', function (Blueprint $table) {
        $table->dropForeign(['released_by']);
        $table->dropColumn(['exception_date_first', 'exception_date_end', 'released_by']);
    });
    }
};
