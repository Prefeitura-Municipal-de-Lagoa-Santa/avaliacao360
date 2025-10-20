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
        Schema::table('evaluation_recourses', function (Blueprint $table) {
            // Prazo para interpor a segunda instância (15 dias após ciência da primeira)
            $table->timestamp('second_instance_deadline_at')->nullable()->after('first_ack_at');
            $table->integer('second_instance_deadline_days')->default(15)->after('second_instance_deadline_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('evaluation_recourses', function (Blueprint $table) {
            $table->dropColumn(['second_instance_deadline_at', 'second_instance_deadline_days']);
        });
    }
};
