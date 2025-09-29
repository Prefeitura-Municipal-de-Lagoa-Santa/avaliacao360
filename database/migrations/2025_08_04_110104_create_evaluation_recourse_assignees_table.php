<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('evaluation_recourse_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recourse_id')->constrained('evaluation_recourses')->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamps();
            
            // Evita duplicação: uma pessoa não pode ser responsável pelo mesmo recurso mais de uma vez
            $table->unique(['recourse_id', 'person_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluation_recourse_assignees');
    }
};
