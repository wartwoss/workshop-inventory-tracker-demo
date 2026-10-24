<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ensure the bedside cabinet model parts are configurable and use the
     * intended formulas. This migration is safe to re-run because it only
     * updates records that already exist; it does not delete stock or orders.
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
                'consumption_unit' => 'm3',
                'formula_key' => 'cabinet_carcass_m3',
                'formula_parameters' => json_encode([
                    'panel_thickness_mm' => 18,
                    'waste_factor' => 1.25,
                ]),
                'is_configurable' => true,
                'sort_order' => 1,
            ],
            'door' => [
                'name' => 'Door / drawer front',
                'material_group' => 'wood',
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
                'consumption_unit' => 'm3',
                'formula_key' => 'solid_volume_m3',
                'formula_parameters' => json_encode([
                    'height_source' => ['type' => 'parameter', 'key' => 'foot_height_mm'],
                    'foot_height_mm' => 60,
                    'profile_width_mm' => 40,
                    'profile_thickness_mm' => 40,
                    'leg_count' => 4,
                    'waste_factor' => 1.15,
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
     * Reverse the migration.
     */
    public function down(): void
    {
        // No-op: the data change is intended to be permanent.
    }
};
