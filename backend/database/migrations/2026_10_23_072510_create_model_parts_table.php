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
        Schema::create('model_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('furniture_model_id')->constrained('furniture_models')->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('material_group');
            $table->string('consumption_unit');
            $table->string('formula_key');
            $table->json('formula_parameters')->nullable();
            $table->timestamps();

            $table->unique(['furniture_model_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('model_parts');
    }
};
