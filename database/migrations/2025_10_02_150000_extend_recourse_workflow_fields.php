<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('evaluation_recourses', function (Blueprint $table) {
            if (!Schema::hasColumn('evaluation_recourses', 'dgp_decision')) {
                $table->string('dgp_decision')->nullable()->after('responded_at');
            }
            if (!Schema::hasColumn('evaluation_recourses', 'dgp_decided_at')) {
                $table->timestamp('dgp_decided_at')->nullable()->after('dgp_decision');
            }
            if (!Schema::hasColumn('evaluation_recourses', 'dgp_notes')) {
                $table->text('dgp_notes')->nullable()->after('dgp_decided_at');
            }
            if (!Schema::hasColumn('evaluation_recourses', 'first_ack_at')) {
                $table->timestamp('first_ack_at')->nullable()->after('dgp_notes');
            }
            if (!Schema::hasColumn('evaluation_recourses', 'is_second_instance')) {
                $table->boolean('is_second_instance')->default(false)->after('first_ack_at');
            }
            if (!Schema::hasColumn('evaluation_recourses', 'second_instance_requested_at')) {
                $table->timestamp('second_instance_requested_at')->nullable()->after('is_second_instance');
            }
            if (!Schema::hasColumn('evaluation_recourses', 'second_instance_text')) {
                $table->text('second_instance_text')->nullable()->after('second_instance_requested_at');
            }
            if (!Schema::hasColumn('evaluation_recourses', 'secretary_decision')) {
                $table->string('secretary_decision')->nullable()->after('second_instance_text');
            }
            if (!Schema::hasColumn('evaluation_recourses', 'secretary_decided_at')) {
                $table->timestamp('secretary_decided_at')->nullable()->after('secretary_decision');
            }
            if (!Schema::hasColumn('evaluation_recourses', 'secretary_notes')) {
                $table->text('secretary_notes')->nullable()->after('secretary_decided_at');
            }
            if (!Schema::hasColumn('evaluation_recourses', 'second_ack_at')) {
                $table->timestamp('second_ack_at')->nullable()->after('secretary_notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_recourses', function (Blueprint $table) {
            $table->dropColumn([
                'dgp_decision',
                'dgp_decided_at',
                'dgp_notes',
                'first_ack_at',
                'is_second_instance',
                'second_instance_requested_at',
                'second_instance_text',
                'secretary_decision',
                'secretary_decided_at',
                'secretary_notes',
                'second_ack_at',
            ]);
        });
    }
};
