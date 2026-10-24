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
        Schema::table('furniture_models', function (Blueprint $table) {
            $table->unsignedInteger('default_length_mm')->default(0)->after('model_asset_path');
            $table->unsignedInteger('min_length_mm')->default(0)->after('default_length_mm');
            $table->unsignedInteger('max_length_mm')->default(0)->after('min_length_mm');
            $table->unsignedInteger('default_width_mm')->default(0)->after('max_length_mm');
            $table->unsignedInteger('min_width_mm')->default(0)->after('default_width_mm');
            $table->unsignedInteger('max_width_mm')->default(0)->after('min_width_mm');
            $table->unsignedInteger('default_height_mm')->default(0)->after('max_width_mm');
            $table->unsignedInteger('min_height_mm')->default(0)->after('default_height_mm');
            $table->unsignedInteger('max_height_mm')->default(0)->after('min_height_mm');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('furniture_models', function (Blueprint $table) {
            $table->dropColumn([
                'default_length_mm',
                'min_length_mm',
                'max_length_mm',
                'default_width_mm',
                'min_width_mm',
                'max_width_mm',
                'default_height_mm',
                'min_height_mm',
                'max_height_mm',
            ]);
        });
    }
};
