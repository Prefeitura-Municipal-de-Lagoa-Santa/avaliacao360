<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('evaluation_requests', function (Blueprint $table) {
            $table->longText('assinatura_base64')->nullable()->after('evidencias');
        });
    }

    public function down()
    {
        Schema::table('evaluation_requests', function (Blueprint $table) {
            $table->dropColumn('assinatura_base64');
        });
    }

};
