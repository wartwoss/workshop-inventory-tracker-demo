<?php

namespace Database\Seeders;

use App\Models\FurnitureModel;
use App\Models\ModelPart;
use Illuminate\Database\Seeder;

/**
 * Adds the optional Office Desk and TV Stand presets without reseeding or
 * modifying any existing model, material, inventory movement, or order.
 */
class AdditionalPremadeModelsSeeder extends Seeder
{
    public function run(): void
    {
        $models = [
            'office-desk' => [
                'slug' => 'office-desk',
                'is_custom' => false,
                'name' => 'Office desk',
                'model_asset_path' => null,
                'is_active' => true,
                'default_length_mm' => 1200,
                'min_length_mm' => 900,
                'max_length_mm' => 1800,
                'default_width_mm' => 600,
                'min_width_mm' => 450,
                'max_width_mm' => 850,
                'default_height_mm' => 750,
                'min_height_mm' => 720,
                'max_height_mm' => 800,
            ],
            'tv-stand' => [
                'slug' => 'tv-stand',
                'is_custom' => false,
                'name' => 'TV stand',
                'model_asset_path' => null,
                'is_active' => true,
                'default_length_mm' => 1400,
                'min_length_mm' => 1000,
                'max_length_mm' => 2000,
                'default_width_mm' => 400,
                'min_width_mm' => 300,
                'max_width_mm' => 550,
                'default_height_mm' => 500,
                'min_height_mm' => 400,
                'max_height_mm' => 650,
            ],
        ];

        foreach ($models as $slug => $attributes) {
            $model = FurnitureModel::updateOrCreate(['slug' => $slug], $attributes);
            $definitions = $this->parts()[$slug];

            foreach ($definitions as $part) {
                ModelPart::updateOrCreate(
                    ['furniture_model_id' => $model->id, 'code' => $part['code']],
                    $part
                );
            }
        }
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function parts(): array
    {
        return [
            'office-desk' => [
                [
                    'code' => 'top',
                    'name' => 'Desk top',
                    'component_type' => 'board',
                    'material_group' => 'wood',
                    'compatible_purposes' => ['board'],
                    'consumption_unit' => 'm2',
                    'formula_key' => 'panel_area_m2',
                    'formula_parameters' => [
                        'dimension_axes' => ['length', 'width'],
                        'waste_factor' => 1.1,
                    ],
                    'is_configurable' => true,
                    'sort_order' => 1,
                ],
                [
                    'code' => 'supports',
                    'name' => 'Side-panel supports (2)',
                    'component_type' => 'board',
                    'material_group' => 'wood',
                    'compatible_purposes' => ['board'],
                    'consumption_unit' => 'm2',
                    'formula_key' => 'panel_area_m2',
                    'formula_parameters' => [
                        'dimension_axes' => ['height', 'width'],
                        'dimension_subtractions_mm' => ['height' => 28],
                        'count_per_unit' => 2,
                        'waste_factor' => 1.1,
                    ],
                    'is_configurable' => true,
                    'sort_order' => 2,
                ],
                [
                    'code' => 'shelf',
                    'name' => 'Lower shelf',
                    'component_type' => 'board',
                    'material_group' => 'wood',
                    'compatible_purposes' => ['board'],
                    'consumption_unit' => 'm2',
                    'formula_key' => 'panel_area_m2',
                    'formula_parameters' => [
                        'dimension_axes' => ['length', 'width'],
                        'dimension_subtractions_mm' => ['length' => 36, 'width' => 36],
                        'waste_factor' => 1.1,
                    ],
                    'is_configurable' => true,
                    'sort_order' => 3,
                ],
            ],
            'tv-stand' => [
                [
                    'code' => 'top',
                    'name' => 'Top panel',
                    'component_type' => 'board',
                    'material_group' => 'wood',
                    'compatible_purposes' => ['board'],
                    'consumption_unit' => 'm2',
                    'formula_key' => 'panel_area_m2',
                    'formula_parameters' => [
                        'dimension_axes' => ['length', 'width'],
                        'waste_factor' => 1.1,
                    ],
                    'is_configurable' => true,
                    'sort_order' => 1,
                ],
                [
                    'code' => 'bottom',
                    'name' => 'Bottom panel',
                    'component_type' => 'board',
                    'material_group' => 'wood',
                    'compatible_purposes' => ['board'],
                    'consumption_unit' => 'm2',
                    'formula_key' => 'panel_area_m2',
                    'formula_parameters' => [
                        'dimension_axes' => ['length', 'width'],
                        'dimension_subtractions_mm' => ['length' => 36],
                        'waste_factor' => 1.1,
                    ],
                    'is_configurable' => true,
                    'sort_order' => 2,
                ],
                [
                    'code' => 'sides',
                    'name' => 'Side panels (2)',
                    'component_type' => 'board',
                    'material_group' => 'wood',
                    'compatible_purposes' => ['board'],
                    'consumption_unit' => 'm2',
                    'formula_key' => 'panel_area_m2',
                    'formula_parameters' => [
                        'dimension_axes' => ['height', 'width'],
                        'dimension_subtractions_mm' => ['height' => 106],
                        'count_per_unit' => 2,
                        'waste_factor' => 1.1,
                    ],
                    'is_configurable' => true,
                    'sort_order' => 3,
                ],
                [
                    'code' => 'shelves',
                    'name' => 'Six shelf segments',
                    'component_type' => 'board',
                    'material_group' => 'wood',
                    'compatible_purposes' => ['board'],
                    'consumption_unit' => 'm2',
                    'formula_key' => 'panel_area_m2',
                    'formula_parameters' => [
                        // Six equal bays have the same aggregate area as two full-length panels
                        // after subtracting the side and two divider thicknesses.
                        'dimension_axes' => ['length', 'width'],
                        'dimension_subtractions_mm' => ['length' => 72],
                        'count_per_unit' => 2,
                        'waste_factor' => 1.1,
                    ],
                    'is_configurable' => true,
                    'sort_order' => 4,
                ],
                [
                    'code' => 'dividers',
                    'name' => 'Vertical dividers (2)',
                    'component_type' => 'board',
                    'material_group' => 'wood',
                    'compatible_purposes' => ['board'],
                    'consumption_unit' => 'm2',
                    'formula_key' => 'panel_area_m2',
                    'formula_parameters' => [
                        'dimension_axes' => ['height', 'width'],
                        'dimension_subtractions_mm' => ['height' => 106],
                        'count_per_unit' => 2,
                        'waste_factor' => 1.1,
                    ],
                    'is_configurable' => true,
                    'sort_order' => 5,
                ],
                [
                    'code' => 'legs',
                    'name' => 'Short legs (4)',
                    'component_type' => 'leg',
                    'material_group' => 'wood',
                    'compatible_purposes' => ['leg'],
                    'consumption_unit' => 'ea',
                    'formula_key' => 'fixed_each',
                    'formula_parameters' => ['count_per_unit' => 4],
                    'is_configurable' => true,
                    'sort_order' => 6,
                ],
            ],
        ];
    }
}
