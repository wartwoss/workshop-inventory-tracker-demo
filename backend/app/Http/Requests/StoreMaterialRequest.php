<?php

namespace App\Http\Requests;

use App\Models\Material;
use App\Services\StockAdjustmentService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaterialRequest extends FormRequest
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
        $quantityRules = $this->input('stock_unit') === 'ea'
            ? ['nullable', 'integer', 'min:0']
            : ['nullable', 'numeric', 'min:0'];

        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255', 'unique:materials,sku'],
            'material_group' => ['required', 'string', 'max:255'],
            'purpose' => ['required', 'string', 'max:50', Rule::in(Material::PURPOSES)],
            'stock_unit' => ['required', 'string', Rule::in(StockAdjustmentService::SUPPORTED_UNITS)],
            'opening_quantity' => $quantityRules,
            'reorder_level' => $quantityRules,
            'visual_key' => ['nullable', 'string', 'max:255'],
            'color_hex' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'texture' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $purpose = $this->input('purpose');
            $stockUnit = $this->input('stock_unit');

            $allowedUnits = Material::PURPOSE_UNITS[$purpose] ?? [];

            if ($purpose && $stockUnit && ! in_array($stockUnit, $allowedUnits, true)) {
                $validator->errors()->add(
                    'stock_unit',
                    "A {$purpose} material must use one of: " . implode(', ', $allowedUnits) . "."
                );
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('is_active')) {
            $this->merge(['is_active' => true]);
        }
    }
}

