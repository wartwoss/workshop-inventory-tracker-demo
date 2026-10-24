<?php

namespace Database\Seeders;

use App\Models\FurnitureModel;
use App\Models\Material;
use App\Models\ModelPart;
use Illuminate\Database\Seeder;

class ModelPartSeeder extends Seeder
{
    /**
     * Seed the configurable parts for each furniture model.
     *
     * Compatibility is resolved at runtime by matching the part's
     * component_type, material_group and consumption_unit against active
     * materials.
     */
    public function run(): void
    {
        $definitions = [
            'table' => [
                [
                    'code' => 'top',
                    'name' => 'Tabletop',
                    'component_type' => 'board',
                    'material_group' => 'wood',
                    'compatible_purposes' => ['board'],
                    'consumption_unit' => 'm2',
                    'formula_key' => 'panel_area_m2',
                    'formula_parameters' => [
                        'dimension_axes' => ['length', 'width'],
                        'waste_factor' => 1.15,
                    ],
                    'is_configurable' => true,
                    'sort_order' => 1,
                ],
                [
                    'code' => 'legs',
                    'name' => 'Legs',
                    'component_type' => 'leg',
                    'material_group' => 'wood',
                    'compatible_purposes' => ['leg'],
                    'consumption_unit' => 'ea',
                    'formula_key' => 'fixed_each',
                    'formula_parameters' => [
                        'count_per_unit' => 4,
                    ],
                    'is_configurable' => true,
                    'sort_order' => 2,
                ],
            ],
            'bedside-cabinet' => [
                [
                    'code' => 'carcass',
                    'name' => 'Carcass',
                    'component_type' => 'board',
                    'material_group' => 'wood',
                    'compatible_purposes' => ['board'],
                    'consumption_unit' => 'm2',
                    'formula_key' => 'cabinet_carcass_m2',
                    'formula_parameters' => [
                        'waste_factor' => 1.25,
                    ],
                    'is_configurable' => true,
                    'sort_order' => 1,
                ],
                [
                    'code' => 'door',
                    'name' => 'Door / drawer front',
                    'component_type' => 'board',
                    'material_group' => 'wood',
                    'compatible_purposes' => ['board'],
                    'consumption_unit' => 'm2',
                    'formula_key' => 'panel_area_m2',
                    'formula_parameters' => [
                        'dimension_axes' => ['length', 'height'],
                        'waste_factor' => 1.1,
                    ],
                    'is_configurable' => true,
                    'sort_order' => 2,
                ],
                [
                    'code' => 'handle',
                    'name' => 'Handle',
                    'component_type' => 'handle',
                    'material_group' => 'metal',
                    'compatible_purposes' => ['handle'],
                    'consumption_unit' => 'ea',
                    'formula_key' => 'fixed_each',
                    'formula_parameters' => [
                        'count_per_unit' => 1,
                    ],
                    'is_configurable' => true,
                    'sort_order' => 3,
                ],
                [
                    'code' => 'feet',
                    'name' => 'Feet',
                    'component_type' => 'leg',
                    'material_group' => 'wood',
                    'compatible_purposes' => ['leg'],
                    'consumption_unit' => 'ea',
                    'formula_key' => 'fixed_each',
                    'formula_parameters' => [
                        'count_per_unit' => 4,
                    ],
                    'is_configurable' => true,
                    'sort_order' => 4,
                ],
            ],
            'coffee-table' => [
                [
                    'code' => 'top',
                    'name' => 'Tabletop',
                    'component_type' => 'board',
                    'material_group' => 'wood',
                    'compatible_purposes' => ['board'],
                    'consumption_unit' => 'm2',
                    'formula_key' => 'panel_area_m2',
                    'formula_parameters' => [
                        'dimension_axes' => ['length', 'width'],
                        'waste_factor' => 1.15,
                    ],
                    'is_configurable' => true,
                    'sort_order' => 1,
                ],
                [
                    'code' => 'legs',
                    'name' => 'Legs',
                    'component_type' => 'leg',
                    'material_group' => 'wood',
                    'compatible_purposes' => ['leg'],
                    'consumption_unit' => 'ea',
                    'formula_key' => 'fixed_each',
                    'formula_parameters' => [
                        'count_per_unit' => 4,
                    ],
                    'is_configurable' => true,
                    'sort_order' => 2,
                ],
            ],
            'bookshelf' => [
                [
                    'code' => 'sides',
                    'name' => 'Side panels',
                    'component_type' => 'board',
                    'material_group' => 'wood',
                    'compatible_purposes' => ['board'],
                    'consumption_unit' => 'm2',
                    'formula_key' => 'panel_area_m2',
                    'formula_parameters' => [
                        'dimension_axes' => ['height', 'width'],
                        'count_per_unit' => 2,
                        'waste_factor' => 1.1,
                    ],
                    'is_configurable' => true,
                    'sort_order' => 1,
                ],
                [
                    'code' => 'topBottom',
                    'name' => 'Top and bottom panels',
                    'component_type' => 'board',
                    'material_group' => 'wood',
                    'compatible_purposes' => ['board'],
                    'consumption_unit' => 'm2',
                    'formula_key' => 'panel_area_m2',
                    'formula_parameters' => [
                        'dimension_axes' => ['length', 'width'],
                        'dimension_subtractions_mm' => ['length' => 36],
                        'count_per_unit' => 2,
                        'waste_factor' => 1.1,
                    ],
                    'is_configurable' => true,
                    'sort_order' => 2,
                ],
                [
                    'code' => 'shelves',
                    'name' => 'Interior shelves (3)',
                    'component_type' => 'board',
                    'material_group' => 'wood',
                    'compatible_purposes' => ['board'],
                    'consumption_unit' => 'm2',
                    'formula_key' => 'panel_area_m2',
                    'formula_parameters' => [
                        'dimension_axes' => ['length', 'width'],
                        'dimension_subtractions_mm' => ['length' => 36, 'width' => 12],
                        'count_per_unit' => 3,
                        'waste_factor' => 1.1,
                    ],
                    'is_configurable' => true,
                    'sort_order' => 3,
                ],
                [
                    'code' => 'back',
                    'name' => 'Back panel',
                    'component_type' => 'board',
                    'material_group' => 'wood',
                    'compatible_purposes' => ['board'],
                    'consumption_unit' => 'm2',
                    'formula_key' => 'panel_area_m2',
                    'formula_parameters' => [
                        'dimension_axes' => ['length', 'height'],
                        'count_per_unit' => 1,
                        'waste_factor' => 1.1,
                    ],
                    'is_configurable' => true,
                    'sort_order' => 4,
                ],
            ],
        ];

        foreach ($definitions as $slug => $parts) {
            $model = FurnitureModel::where('slug', $slug)->first();

            if (! $model) {
                continue;
            }

            foreach ($parts as $attributes) {
                ModelPart::updateOrCreate(
                    [
                        'furniture_model_id' => $model->id,
                        'code' => $attributes['code'],
                    ],
                    $attributes
                );
            }
        }
    }
}
