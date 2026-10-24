<?php

namespace App\Http\Requests;

use App\Models\Material;
use App\Services\StockAdjustmentService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $material = $this->route('material');
        $materialId = $material instanceof Material ? $material->getKey() : $material;
        $stockUnit = $this->input('stock_unit', $material instanceof Material ? $material->stock_unit : null);
        $reorderLevelRules = $stockUnit === 'ea'
            ? ['nullable', 'integer', 'min:0']
            : ['nullable', 'numeric', 'min:0'];

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255', Rule::unique('materials', 'sku')->ignore($materialId)],
            'material_group' => ['sometimes', 'required', 'string', 'max:255'],
            'purpose' => ['sometimes', 'required', 'string', 'max:50', Rule::in(Material::PURPOSES)],
            'stock_unit' => ['sometimes', 'required', 'string', Rule::in(StockAdjustmentService::SUPPORTED_UNITS)],
            'reorder_level' => $reorderLevelRules,
            'visual_key' => ['nullable', 'string', 'max:255'],
            'color_hex' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'texture' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'remove_texture' => ['nullable', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            /** @var Material|null $material */
            $material = $this->route('material');
            $purpose = $this->input('purpose', $material instanceof Material ? $material->purpose : null);
            $stockUnit = $this->input('stock_unit', $material instanceof Material ? $material->stock_unit : null);

            $allowedUnits = Material::PURPOSE_UNITS[$purpose] ?? [];

            if ($purpose && $stockUnit && ! in_array($stockUnit, $allowedUnits, true)) {
                $validator->errors()->add(
                    'stock_unit',
                    "A {$purpose} material must use one of: " . implode(', ', $allowedUnits) . "."
                );
            }
        });
    }
}

