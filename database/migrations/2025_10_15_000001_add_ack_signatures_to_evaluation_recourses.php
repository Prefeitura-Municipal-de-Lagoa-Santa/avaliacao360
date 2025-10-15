<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('evaluation_recourses', function (Blueprint $table) {
            // Store base64 data URLs for signatures similar to Acknowledgment model usage
            $table->longText('first_ack_signature_base64')->nullable()->after('first_ack_at');
            $table->longText('second_ack_signature_base64')->nullable()->after('second_ack_at');
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_recourses', function (Blueprint $table) {
            $table->dropColumn('first_ack_signature_base64');
            $table->dropColumn('second_ack_signature_base64');
        });
    }
};
