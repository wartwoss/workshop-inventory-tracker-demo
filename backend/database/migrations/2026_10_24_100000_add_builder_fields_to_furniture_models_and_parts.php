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
            $table->boolean('is_custom')
                ->default(false)
                ->after('is_active');
        });

        Schema::table('model_parts', function (Blueprint $table) {
            $table->string('component_type', 50)
                ->nullable()
                ->after('code');

            $table->foreignId('material_id')
                ->nullable()
                ->after('consumption_unit')
                ->constrained('materials')
                ->nullOnDelete();

            $table->integer('length_mm')->nullable()->after('material_id');
            $table->integer('width_mm')->nullable()->after('length_mm');
            $table->integer('height_mm')->nullable()->after('width_mm');

            $table->integer('pos_x_mm')->default(0)->after('height_mm');
            $table->integer('pos_y_mm')->default(0)->after('pos_x_mm');
            $table->integer('pos_z_mm')->default(0)->after('pos_y_mm');

            $table->integer('rot_x_deg')->default(0)->after('pos_z_mm');
            $table->integer('rot_y_deg')->default(0)->after('rot_x_deg');
            $table->integer('rot_z_deg')->default(0)->after('rot_y_deg');

            $table->string('shape', 50)->nullable()->after('rot_z_deg');
            $table->string('label', 255)->nullable()->after('shape');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('model_parts', function (Blueprint $table) {
            $table->dropColumn([
                'component_type',
                'material_id',
                'length_mm',
                'width_mm',
                'height_mm',
                'pos_x_mm',
                'pos_y_mm',
                'pos_z_mm',
                'rot_x_deg',
                'rot_y_deg',
                'rot_z_deg',
                'shape',
                'label',
            ]);
        });

        Schema::table('furniture_models', function (Blueprint $table) {
            $table->dropColumn('is_custom');
        });
    }
};
