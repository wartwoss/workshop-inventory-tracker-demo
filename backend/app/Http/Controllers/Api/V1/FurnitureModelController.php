<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFurnitureModelRequest;
use App\Models\FurnitureModel;
use App\Models\Material;
use App\Models\ModelPart;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FurnitureModelController extends Controller
{
    public function index(): JsonResponse
    {
        $models = FurnitureModel::active()
            ->orderBy('name')
            ->get([
                'id',
                'slug',
                'name',
                'model_asset_path',
                'is_custom',
                'default_length_mm',
                'min_length_mm',
                'max_length_mm',
                'default_width_mm',
                'min_width_mm',
                'max_width_mm',
                'default_height_mm',
                'min_height_mm',
                'max_height_mm',
                'is_active',
            ]);

        return response()->json($models);
    }

    public function store(StoreFurnitureModelRequest $request): JsonResponse
    {
        $data = $request->validated();

        $slug = $this->uniqueSlug(Str::slug($data['name']));

        $model = DB::transaction(function () use ($data, $slug) {
            $model = FurnitureModel::create([
                'slug' => $slug,
                'name' => $data['name'],
                'model_asset_path' => null,
                'is_active' => true,
                'is_custom' => true,
                'default_length_mm' => 1000,
                'min_length_mm' => 1,
                'max_length_mm' => 5000,
                'default_width_mm' => 500,
                'min_width_mm' => 1,
                'max_width_mm' => 5000,
                'default_height_mm' => 1000,
                'min_height_mm' => 1,
                'max_height_mm' => 5000,
            ]);

            $this->persistComponents($model, $data['components']);

            return $model;
        });

        return response()->json($model->load('modelParts'), 201);
    }

    public function show(string $slug): JsonResponse
    {
        $model = FurnitureModel::active()
            ->where('slug', $slug)
            ->with(['modelParts' => function ($query) {
                $query->where('is_configurable', true)
                    ->orderBy('sort_order')
                    ->orderBy('name');
            }])
            ->firstOrFail();

        $parts = $model->modelParts->map(function ($part) use ($model) {
            if ($model->is_custom) {
                return $this->serializeCustomPart($part);
            }

            return $this->serializeLegacyPart($part);
        });

        return response()->json([
            'id' => $model->id,
            'slug' => $model->slug,
            'name' => $model->name,
            'is_custom' => $model->is_custom,
            'model_asset_path' => $model->model_asset_path,
            'dimensions' => [
                'length' => [
                    'default' => $model->default_length_mm,
                    'min' => $model->min_length_mm,
                    'max' => $model->max_length_mm,
                    'unit' => 'mm',
                ],
                'width' => [
                    'default' => $model->default_width_mm,
                    'min' => $model->min_width_mm,
                    'max' => $model->max_width_mm,
                    'unit' => 'mm',
                ],
                'height' => [
                    'default' => $model->default_height_mm,
                    'min' => $model->min_height_mm,
                    'max' => $model->max_height_mm,
                    'unit' => 'mm',
                ],
            ],
            'parts' => $parts,
        ]);
    }

    public function destroy(FurnitureModel $furnitureModel): JsonResponse
    {
        if (! $furnitureModel->is_custom) {
            return response()->json([
                'message' => 'Only custom furniture models can be deleted from this interface.',
            ], 403);
        }

        $result = DB::transaction(function () use ($furnitureModel) {
            $model = FurnitureModel::query()->lockForUpdate()->findOrFail($furnitureModel->id);
            $hasOrders = $model->productionOrders()->exists();
            $hasConsumptionHistory = ModelPart::where('furniture_model_id', $model->id)
                ->whereHas('productionConsumptions')
                ->exists();

            if ($hasOrders || $hasConsumptionHistory) {
                $model->update(['is_active' => false]);

                return [
                    'deleted' => false,
                    'archived' => true,
                    'message' => "{$model->name} was archived to preserve production history.",
                ];
            }

            $name = $model->name;
            $model->delete();

            return [
                'deleted' => true,
                'archived' => false,
                'message' => "{$name} was permanently deleted.",
            ];
        });

        return response()->json($result);
    }

    public function update(StoreFurnitureModelRequest $request, FurnitureModel $furnitureModel): JsonResponse
    {
        if (! $furnitureModel->is_custom) {
            return response()->json(['message' => 'Only custom furniture models can be updated through this endpoint.'], 403);
        }

        $data = $request->validated();

        DB::transaction(function () use ($furnitureModel, $data) {
            $furnitureModel->update(['name' => $data['name']]);
            $furnitureModel->modelParts()->delete();
            $this->persistComponents($furnitureModel, $data['components']);
        });

        return response()->json($furnitureModel->load('modelParts'));
    }

    /**
     * @param array<int, array<string, mixed>> $components
     */
    private function persistComponents(FurnitureModel $model, array $components): void
    {
        foreach ($components as $index => $component) {
            $type = $component['type'];
            $material = Material::findOrFail($component['material_id']);

            ModelPart::create([
                'furniture_model_id' => $model->id,
                'code' => "{$type}-" . ($index + 1),
                'name' => $component['label'],
                'component_type' => $type,
                'material_group' => $material->material_group,
                'compatible_purposes' => [$type],
                'consumption_unit' => $material->stock_unit,
                'formula_key' => $this->builderFormulaKey($type),
                'formula_parameters' => [],
                'is_configurable' => true,
                'sort_order' => $index,
                'material_id' => $material->id,
                'length_mm' => $component['length_mm'],
                'width_mm' => $component['width_mm'],
                'height_mm' => $component['height_mm'],
                'pos_x_mm' => $component['pos_x_mm'] ?? 0,
                'pos_y_mm' => $component['pos_y_mm'] ?? 0,
                'pos_z_mm' => $component['pos_z_mm'] ?? 0,
                'rot_x_deg' => $component['rot_x_deg'] ?? 0,
                'rot_y_deg' => $component['rot_y_deg'] ?? 0,
                'rot_z_deg' => $component['rot_z_deg'] ?? 0,
                'shape' => $component['shape'] ?? null,
                'label' => $component['label'],
            ]);
        }
    }

    private function builderFormulaKey(string $type): string
    {
        return match ($type) {
            'board' => 'builder_board_area_m2',
            'leg' => 'builder_leg_each',
            'handle' => 'builder_handle_each',
            default => 'builder_board_area_m2',
        };
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base;
        $counter = 2;

        while (FurnitureModel::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    private function serializeCustomPart(ModelPart $part): array
    {
        $compatiblePurposes = $part->compatible_purposes ?? [$part->component_type];

        $allowedUnits = $part->component_type === 'board' ? ['m2'] : ['ea'];

        $materials = Material::active()
            ->where('purpose', $part->component_type)
            ->whereIn('stock_unit', $allowedUnits)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'sku',
                'material_group',
                'purpose',
                'stock_unit',
                'quantity_on_hand',
                'visual_key',
                'color_hex',
                'texture_path',
                'is_active',
            ]);

        return [
            'id' => $part->id,
            'code' => $part->code,
            'name' => $part->name,
            'component_type' => $part->component_type,
            'material_group' => $part->material_group,
            'consumption_unit' => $part->consumption_unit,
            'compatible_purposes' => $compatiblePurposes,
            'formula_key' => $part->formula_key,
            'formula_parameters' => $part->formula_parameters,
            'is_configurable' => $part->is_configurable,
            'sort_order' => $part->sort_order,
            'material_id' => $part->material_id,
            'length_mm' => $part->length_mm,
            'width_mm' => $part->width_mm,
            'height_mm' => $part->height_mm,
            'pos_x_mm' => $part->pos_x_mm,
            'pos_y_mm' => $part->pos_y_mm,
            'pos_z_mm' => $part->pos_z_mm,
            'rot_x_deg' => $part->rot_x_deg,
            'rot_y_deg' => $part->rot_y_deg,
            'rot_z_deg' => $part->rot_z_deg,
            'shape' => $part->shape ?? match ($part->component_type) {
                'board' => 'rectangular',
                'leg' => 'rectangular_leg',
                'handle' => 'bar',
                default => 'rectangular',
            },
            'label' => $part->label,
            'allowed_materials' => $materials,
            'has_compatible_materials' => $materials->isNotEmpty(),
        ];
    }

    private function serializeLegacyPart(ModelPart $part): array
    {
        $compatiblePurposes = $part->compatible_purposes ?? [\App\Models\Material::PURPOSE_BOARD];

        $materials = Material::active()
            ->where('material_group', $part->material_group)
            ->where('stock_unit', $part->consumption_unit)
            ->where('purpose', '!=', \App\Models\Material::PURPOSE_OTHER)
            ->where(function ($query) use ($compatiblePurposes) {
                $query->whereNull('purpose')
                    ->orWhereIn('purpose', $compatiblePurposes);
            })
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'sku',
                'material_group',
                'purpose',
                'stock_unit',
                'quantity_on_hand',
                'visual_key',
                'color_hex',
                'texture_path',
                'is_active',
            ]);

        return [
            'id' => $part->id,
            'code' => $part->code,
            'name' => $part->name,
            'component_type' => $part->component_type,
            'material_group' => $part->material_group,
            'compatible_purposes' => $compatiblePurposes,
            'consumption_unit' => $part->consumption_unit,
            'formula_key' => $part->formula_key,
            'formula_parameters' => $part->formula_parameters,
            'is_configurable' => $part->is_configurable,
            'sort_order' => $part->sort_order,
            'allowed_materials' => $materials,
            'has_compatible_materials' => $materials->isNotEmpty(),
        ];
    }
}
