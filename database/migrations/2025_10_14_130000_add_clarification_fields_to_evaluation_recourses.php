<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('evaluation_recourses', function (Blueprint $table) {
            if (!Schema::hasColumn('evaluation_recourses', 'clarification_response')) {
                $table->text('clarification_response')->nullable()->after('commission_decided_at');
            }
            if (!Schema::hasColumn('evaluation_recourses', 'clarification_responded_at')) {
                $table->timestamp('clarification_responded_at')->nullable()->after('clarification_response');
            }
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_recourses', function (Blueprint $table) {
            if (Schema::hasColumn('evaluation_recourses', 'clarification_response')) {
                $table->dropColumn('clarification_response');
            }
            if (Schema::hasColumn('evaluation_recourses', 'clarification_responded_at')) {
                $table->dropColumn('clarification_responded_at');
            }
        });
    }
};
