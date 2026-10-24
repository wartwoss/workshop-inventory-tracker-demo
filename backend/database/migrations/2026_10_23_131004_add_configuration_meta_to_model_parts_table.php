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
        Schema::table('model_parts', function (Blueprint $table) {
            $table->boolean('is_configurable')->default(true)->after('formula_parameters');
            $table->unsignedTinyInteger('sort_order')->default(0)->after('is_configurable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('model_parts', function (Blueprint $table) {
            $table->dropColumn(['is_configurable', 'sort_order']);
        });
    }
};
