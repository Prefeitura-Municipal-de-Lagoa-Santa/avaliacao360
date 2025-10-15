<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('evaluation_recourses', function (Blueprint $table) {
            if (!Schema::hasColumn('evaluation_recourses', 'last_return_message')) {
                $table->text('last_return_message')->nullable()->after('last_returned_at');
            }
        });

        Schema::table('evaluation_recourse_attachments', function (Blueprint $table) {
            if (!Schema::hasColumn('evaluation_recourse_attachments', 'context')) {
                $table->string('context', 40)->nullable()->after('original_name'); // e.g. forward, dgp_return, commission_return
            }
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_recourses', function (Blueprint $table) {
            if (Schema::hasColumn('evaluation_recourses', 'last_return_message')) {
                $table->dropColumn('last_return_message');
            }
        });
        Schema::table('evaluation_recourse_attachments', function (Blueprint $table) {
            if (Schema::hasColumn('evaluation_recourse_attachments', 'context')) {
                $table->dropColumn('context');
            }
        });
    }
};
