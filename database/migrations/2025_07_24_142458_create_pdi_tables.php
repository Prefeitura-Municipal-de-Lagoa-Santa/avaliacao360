<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->onDelete('cascade');
            // ALTERAÇÃO AQUI
            $table->foreignId('person_id')->constrained('people')->onDelete('cascade'); // de 'persons' para 'people'
            $table->year('year');
            $table->text('development_goals')->nullable();
            $table->text('actions_needed')->nullable();
            $table->text('manager_feedback')->nullable();
            $table->timestamps();
        });

        Schema::create('pdi_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pdi_id')->constrained('pdis')->onDelete('cascade');
            // ALTERAÇÃO AQUI
            $table->foreignId('person_id')->constrained('people')->onDelete('cascade'); // de 'persons' para 'people'
            // ALTERAÇÃO AQUI
            $table->foreignId('manager_id')->constrained('people')->onDelete('cascade'); // de 'persons' para 'people'
            $table->string('status')->default('pending_manager_fill');
            $table->text('manager_signature_base64')->nullable();
            $table->timestamp('manager_signed_at')->nullable();
            $table->text('person_signature_base64')->nullable();
            $table->timestamp('person_signed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdi_requests');
        Schema::dropIfExists('pdis');
    }
};