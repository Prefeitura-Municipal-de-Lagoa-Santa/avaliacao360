<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAcknowledgmentsTable extends Migration
{
    public function up()
    {
        Schema::create('acknowledgments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained()->onDelete('cascade');
            $table->string('year');
            $table->timestamp('signed_at')->nullable();
            $table->longText('signature_base64')->nullable();
            $table->timestamps();

            $table->unique(['person_id', 'year']); // impede múltiplas assinaturas para o mesmo ano
        });
    }

    public function down()
    {
        Schema::dropIfExists('acknowledgments');
    }
}
