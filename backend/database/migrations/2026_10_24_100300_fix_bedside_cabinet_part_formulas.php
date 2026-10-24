<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Correct the bedside cabinet part definitions after earlier migrations left
     * the feet formula inconsistent and the carcass/door definitions untouched.
     *
     * This migration is safe for existing data: it only updates model-part
     * metadata and leaves stock movements, quotes, and production orders intact.
     */
    public function up(): void
    {
        $model = DB::table('furniture_models')->where('slug', 'bedside-cabinet')->first();

        if (! $model) {
            return;
        }

        $parts = [
            'carcass' => [
                'name' => 'Carcass',
                'material_group' => 'wood',
                'component_type' => 'board',
                'compatible_purposes' => json_encode(['board']),
                'consumption_unit' => 'm2',
                'formula_key' => 'cabinet_carcass_m2',
                'formula_parameters' => json_encode([
                    'waste_factor' => 1.25,
                ]),
                'is_configurable' => true,
                'sort_order' => 1,
            ],
            'door' => [
                'name' => 'Door / drawer front',
                'material_group' => 'wood',
                'component_type' => 'board',
                'compatible_purposes' => json_encode(['board']),
                'consumption_unit' => 'm2',
                'formula_key' => 'panel_area_m2',
                'formula_parameters' => json_encode([
                    'dimension_axes' => ['length', 'height'],
                    'waste_factor' => 1.1,
                ]),
                'is_configurable' => true,
                'sort_order' => 2,
            ],
            'handle' => [
                'name' => 'Handle',
                'material_group' => 'metal',
                'component_type' => 'handle',
                'compatible_purposes' => json_encode(['handle']),
                'consumption_unit' => 'ea',
                'formula_key' => 'fixed_each',
                'formula_parameters' => json_encode([
                    'count_per_unit' => 1,
                ]),
                'is_configurable' => true,
                'sort_order' => 3,
            ],
            'feet' => [
                'name' => 'Feet',
                'material_group' => 'wood',
                'component_type' => 'leg',
                'compatible_purposes' => json_encode(['leg']),
                'consumption_unit' => 'ea',
                'formula_key' => 'fixed_each',
                'formula_parameters' => json_encode([
                    'count_per_unit' => 4,
                ]),
                'is_configurable' => true,
                'sort_order' => 4,
            ],
        ];

        foreach ($parts as $code => $attributes) {
            DB::table('model_parts')
                ->updateOrInsert(
                    [
                        'furniture_model_id' => $model->id,
                        'code' => $code,
                    ],
                    $attributes
                );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data-only correction; reversal is not meaningful.
    }
};
