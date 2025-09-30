<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEvaluationRecourseLogsTable extends Migration
{
    public function up()
    {
        Schema::create('evaluation_recourse_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recourse_id')->constrained('evaluation_recourses')->cascadeOnDelete();
            $table->string('status'); // Ex: aberto, em_analise, respondido, indeferido
            $table->text('message')->nullable(); // Mensagem opcional
            $table->timestamp('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('evaluation_recourse_logs');
    }
}
