<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('evaluation_recourses', function (Blueprint $table) {
            if (!Schema::hasColumn('evaluation_recourses', 'workflow_stage')) {
                $table->string('workflow_stage')->default('rh_analysis')->after('current_instance');
                try {
                    $table->index('workflow_stage');
                } catch (\Throwable $e) { /* ignore */ }
            }
        });

        // Best-effort data migration: copy stage -> workflow_stage when possible
        try {
            DB::statement('UPDATE evaluation_recourses SET workflow_stage = COALESCE(workflow_stage, stage, "rh_analysis")');
        } catch (\Throwable $e) {
            // ignore for SQLite without DB facade in migration context
        }
    }

    public function down(): void
    {
        Schema::table('evaluation_recourses', function (Blueprint $table) {
            try { $table->dropIndex(['workflow_stage']); } catch (\Throwable $e) { /* ignore */ }
            if (Schema::hasColumn('evaluation_recourses', 'workflow_stage')) {
                $table->dropColumn('workflow_stage');
            }
        });
    }
};
