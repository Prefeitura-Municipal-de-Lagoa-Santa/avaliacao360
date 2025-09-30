<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    
    public function up()
    {
        Schema::create('job_functions', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Nome da função/cargo
            $table->string('type')->default('servidor'); // 'chefe' ou 'servidor'
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('job_functions');
    }

};
