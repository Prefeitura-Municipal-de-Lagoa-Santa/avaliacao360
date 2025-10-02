<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('evaluation_recourses', 'stage')) {
            Schema::table('evaluation_recourses', function (Blueprint $table) {
                $table->string('stage')->default('rh_analysis')->after('current_instance');
            });
        }
        // Add index if column exists and index not already present (SQLite tolerant)
        try {
            Schema::table('evaluation_recourses', function (Blueprint $table) {
                $table->index('stage');
            });
        } catch (\Throwable $e) {
            // ignore if index exists or driver limitations
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('evaluation_recourses', 'stage')) {
            // Drop index if possible
            try {
                Schema::table('evaluation_recourses', function (Blueprint $table) {
                    $table->dropIndex(['stage']);
                });
            } catch (\Throwable $e) {
                // ignore
            }
            Schema::table('evaluation_recourses', function (Blueprint $table) {
                $table->dropColumn('stage');
            });
        }
    }
};
