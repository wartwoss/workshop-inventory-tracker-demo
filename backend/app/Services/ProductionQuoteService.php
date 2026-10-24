<?php

namespace App\Services;

use App\Models\FurnitureModel;
use App\Models\Material;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class ProductionQuoteService
{
    public function __construct(private readonly FormulaCalculator $formulaCalculator)
    {
    }

    /**
     * Calculate a production quote without changing inventory.
     *
     * Expected input keys:
     * - furniture_model_slug: string
     * - length_mm, width_mm, height_mm: positive integers
     * - quantity: positive integer
     * - parts: array of ['code' => string, 'material_id' => int]
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function quote(array $input): array
    {
        $model = FurnitureModel::active()
            ->where('slug', $input['furniture_model_slug'])
            ->with(['modelParts' => function ($query) {
                $query->where('is_configurable', true)->orderBy('sort_order');
            }])
            ->first();

        if (! $model) {
            throw new InvalidArgumentException('Furniture model not found or inactive.');
        }

        $dimensions = [
            'length' => (int) $input['length_mm'],
            'width' => (int) $input['width_mm'],
            'height' => (int) $input['height_mm'],
        ];

        $this->validateDimensions($model, $dimensions);

        $quantity = (int) $input['quantity'];

        if ($quantity < 1) {
            throw new InvalidArgumentException('Quantity must be at least 1.');
        }

        $submittedParts = collect($input['parts'] ?? [])->keyBy('code');
        $configurableParts = $model->modelParts;

        $this->validatePartSelections($configurableParts, $submittedParts);

        $components = [];
        $materialAggregates = [];

        $isCustom = (bool) $model->is_custom;

        foreach ($configurableParts as $part) {
            $selection = $submittedParts[$part->code];
            $material = Material::active()->find($selection['material_id']);

            if (! $material) {
                throw new InvalidArgumentException("Selected material for part '{$part->code}' is not available.");
            }

            if ($isCustom) {
                $this->validateCustomComponentMaterial($part, $material);
                $perUnit = $this->calculateCustomComponentConsumption($part, $material, $selection);
                $unit = $material->stock_unit;
            } else {
                if ($material->material_group !== $part->material_group) {
                    throw new InvalidArgumentException(
                        "Material group mismatch for part '{$part->code}'."
                    );
                }

                if ($material->stock_unit !== $part->consumption_unit) {
                    throw new InvalidArgumentException(
                        "Unit mismatch for part '{$part->code}': expected {$part->consumption_unit}, got {$material->stock_unit}."
                    );
                }

                $compatiblePurposes = $part->compatible_purposes ?? [Material::PURPOSE_BOARD];

                if ($material->purpose !== null && ! in_array($material->purpose, $compatiblePurposes, true)) {
                    throw new InvalidArgumentException(
                        "Material purpose '{$material->purpose}' is not compatible with part '{$part->code}'."
                    );
                }

                $perUnit = $this->formulaCalculator->calculate(
                    $part->formula_key,
                    $dimensions,
                    $part->formula_parameters ?? []
                );
                $unit = $part->consumption_unit;
            }

            $total = bcmul($perUnit, (string) $quantity, 6);

            $components[] = [
                'part_id' => $part->id,
                'part_code' => $part->code,
                'part_name' => $part->name,
                'material_id' => $material->id,
                'material_name' => $material->name,
                'unit' => $unit,
                'per_unit_quantity' => $perUnit,
                'total_quantity' => $total,
            ];

            $materialId = $material->id;

            if (! isset($materialAggregates[$materialId])) {
                $materialAggregates[$materialId] = [
                    'material_id' => $material->id,
                    'material_name' => $material->name,
                    'unit' => $material->stock_unit,
                    'required_quantity' => '0.000000',
                    'available_quantity' => $material->quantity_on_hand,
                ];
            }

            $materialAggregates[$materialId]['required_quantity'] = bcadd(
                $materialAggregates[$materialId]['required_quantity'],
                $total,
                6
            );
        }

        $materials = [];
        $isSufficient = true;

        foreach ($materialAggregates as $aggregate) {
            $aggregate['is_sufficient'] = bccomp(
                $aggregate['available_quantity'],
                $aggregate['required_quantity'],
                6
            ) >= 0;

            if (! $aggregate['is_sufficient']) {
                $isSufficient = false;
            }

            $materials[] = $aggregate;
        }

        return [
            'furniture_model' => [
                'id' => $model->id,
                'slug' => $model->slug,
                'name' => $model->name,
            ],
            'configuration' => [
                'length_mm' => $dimensions['length'],
                'width_mm' => $dimensions['width'],
                'height_mm' => $dimensions['height'],
                'quantity' => $quantity,
                'parts' => $this->serializeConfigurationParts($configurableParts, $submittedParts, $isCustom),
            ],
            'components' => $components,
            'materials' => $materials,
            'is_sufficient' => $isSufficient,
        ];
    }

    /**
     * @param FurnitureModel $model
     * @param array<string, int> $dimensions
     */
    private function validateDimensions(FurnitureModel $model, array $dimensions): void
    {
        $constraints = [
            'length' => ['min' => $model->min_length_mm, 'max' => $model->max_length_mm],
            'width' => ['min' => $model->min_width_mm, 'max' => $model->max_width_mm],
            'height' => ['min' => $model->min_height_mm, 'max' => $model->max_height_mm],
        ];

        foreach ($constraints as $axis => $constraint) {
            $value = $dimensions[$axis];

            if ($value < $constraint['min'] || $value > $constraint['max']) {
                throw new InvalidArgumentException(
                    "Dimension '{$axis}' must be between {$constraint['min']} and {$constraint['max']} mm."
                );
            }
        }
    }

    /**
     * @param \Illuminate\Database\Eloquent\Collection $configurableParts
     * @param \Illuminate\Support\Collection $submittedParts
     */
    private function validatePartSelections($configurableParts, $submittedParts): void
    {
        $validCodes = $configurableParts->pluck('code')->flip();

        foreach ($submittedParts->keys() as $code) {
            if (! $validCodes->has($code)) {
                throw new InvalidArgumentException("Unknown or non-configurable part code: {$code}");
            }
        }

        foreach ($configurableParts as $part) {
            if (! $submittedParts->has($part->code)) {
                throw new InvalidArgumentException("Missing material selection for part '{$part->code}'.");
            }

            $selection = $submittedParts[$part->code];

            if (! isset($selection['material_id']) || ! is_numeric($selection['material_id'])) {
                throw new InvalidArgumentException("Invalid material selection for part '{$part->code}'.");
            }
        }
    }

    private function validateCustomComponentMaterial($part, Material $material): void
    {
        $type = $part->component_type;

        if ($material->purpose !== $type) {
            throw new InvalidArgumentException(
                "Material purpose '{$material->purpose}' does not match component type '{$type}' for part '{$part->code}'."
            );
        }

        if ($type === 'board' && $material->stock_unit !== 'm2') {
            throw new InvalidArgumentException(
                "Board component '{$part->code}' requires a material measured in m2."
            );
        }

        if (in_array($type, ['leg', 'handle'], true) && $material->stock_unit !== 'ea') {
            throw new InvalidArgumentException(
                ucfirst($type) . " component '{$part->code}' requires a material measured in ea."
            );
        }
    }

    private const PI = '3.14159265358979323846';

    private function calculateCustomComponentConsumption($part, Material $material, array $selection): string
    {
        // Each order may override the saved template dimensions. Omitted fields
        // intentionally fall back to the template for older clients.
        $length = (int) ($selection['length_mm'] ?? $part->length_mm);
        $width = (int) ($selection['width_mm'] ?? $part->width_mm);
        $height = (int) ($selection['height_mm'] ?? $part->height_mm);
        $defaultShape = match ($part->component_type) {
            'board' => 'rectangular',
            'leg' => 'rectangular_leg',
            'handle' => 'bar',
            default => 'rectangular',
        };
        $shape = (string) ($selection['shape'] ?? $part->shape ?? $defaultShape);

        foreach (['length' => $length, 'width' => $width, 'height' => $height] as $dimension => $value) {
            if ($value < 1 || $value > 5000) {
                throw new InvalidArgumentException("Component '{$part->code}' {$dimension} must be between 1 and 5000 mm.");
            }
        }

        if ($part->component_type === 'board') {
            if (! in_array($shape, ['rectangular', 'circular'], true)) {
                throw new InvalidArgumentException("Invalid board shape for component '{$part->code}'.");
            }

            if ($shape === 'circular') {
                $radiusMm = bcdiv((string) $length, '2', 6);
                $radiusSquared = bcmul($radiusMm, $radiusMm, 6);
                $areaMm2 = bcmul(self::PI, $radiusSquared, 6);

                return bcdiv($areaMm2, '1000000', 6);
            }

            $areaMm2 = bcmul((string) $length, (string) $width, 0);
            return bcdiv($areaMm2, '1000000', 6);
        }

        if ($part->component_type === 'leg' && ! in_array($shape, [
            'cube_foot', 'angular_foot', 'round_foot', 'rectangular_leg', 'cylinder_leg',
        ], true)) {
            throw new InvalidArgumentException("Invalid leg/foot shape for component '{$part->code}'.");
        }

        if ($part->component_type === 'handle' && ! in_array($shape, ['knob', 'bulb_knob', 'bar'], true)) {
            throw new InvalidArgumentException("Invalid handle shape for component '{$part->code}'.");
        }

        // Legs/feet and handles are counted as individual pieces, never by volume.
        return '1.000000';
    }

    /**
     * @param \Illuminate\Support\Collection<int, \App\Models\ModelPart> $configurableParts
     * @param \Illuminate\Support\Collection<string, array<string, mixed>> $submittedParts
     * @return array<int, array<string, mixed>>
     */
    private function serializeConfigurationParts($configurableParts, $submittedParts, bool $isCustom): array
    {
        return $configurableParts->map(function ($part) use ($submittedParts, $isCustom) {
            $selection = $submittedParts[$part->code];
            $item = [
                'code' => $part->code,
                'material_id' => (int) $selection['material_id'],
            ];

            if ($isCustom) {
                foreach ([
                    'length_mm' => $part->length_mm,
                    'width_mm' => $part->width_mm,
                    'height_mm' => $part->height_mm,
                    'pos_x_mm' => $part->pos_x_mm,
                    'pos_y_mm' => $part->pos_y_mm,
                    'pos_z_mm' => $part->pos_z_mm,
                    'rot_x_deg' => $part->rot_x_deg,
                    'rot_y_deg' => $part->rot_y_deg,
                    'rot_z_deg' => $part->rot_z_deg,
                ] as $key => $default) {
                    $item[$key] = (int) ($selection[$key] ?? $default ?? 0);
                }
                $item['shape'] = (string) ($selection['shape'] ?? $part->shape ?? match ($part->component_type) {
                    'board' => 'rectangular',
                    'leg' => 'rectangular_leg',
                    'handle' => 'bar',
                    default => 'rectangular',
                });
            }

            return $item;
        })->values()->all();
    }
}
