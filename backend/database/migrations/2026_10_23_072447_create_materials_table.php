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
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku')->nullable()->unique();
            $table->string('material_group');
            $table->string('stock_unit');
            $table->decimal('quantity_on_hand', 18, 6)->default(0);
            $table->decimal('reorder_level', 18, 6)->nullable();
            $table->string('visual_key')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['material_group', 'stock_unit']);
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};
