<?php

namespace App\Http\Requests;

use App\Models\Material;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFurnitureModelRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'components' => ['required', 'array', 'min:1'],
            'components.*.type' => ['required', 'string', Rule::in(['board', 'leg', 'handle'])],
            'components.*.label' => ['required', 'string', 'max:255'],
            'components.*.material_id' => ['required', 'integer', 'exists:materials,id'],
            'components.*.length_mm' => ['required', 'integer', 'min:1'],
            'components.*.width_mm' => ['required', 'integer', 'min:1'],
            'components.*.height_mm' => ['required', 'integer', 'min:1'],
            'components.*.pos_x_mm' => ['integer'],
            'components.*.pos_y_mm' => ['integer'],
            'components.*.pos_z_mm' => ['integer'],
            'components.*.rot_x_deg' => ['integer'],
            'components.*.rot_y_deg' => ['integer'],
            'components.*.rot_z_deg' => ['integer'],
            'components.*.shape' => ['nullable', 'string', Rule::in(array_merge(
                ['rectangular', 'circular'],
                ['cube_foot', 'angular_foot', 'round_foot', 'rectangular_leg', 'cylinder_leg'],
                ['knob', 'bulb_knob', 'bar']
            ))],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $components = $this->input('components', []);

            foreach ($components as $index => $component) {
                $type = $component['type'] ?? null;
                $materialId = $component['material_id'] ?? null;
                $shape = $component['shape'] ?? null;

                if (! $type || ! $materialId) {
                    continue;
                }

                $material = Material::find($materialId);

                if (! $material) {
                    $validator->errors()->add("components.{$index}.material_id", 'Selected material not found.');
                    continue;
                }

                if (! $material->is_active) {
                    $validator->errors()->add("components.{$index}.material_id", 'Inactive materials cannot be assigned to a new furniture model.');
                    continue;
                }

                if ($material->purpose !== $type) {
                    $validator->errors()->add(
                        "components.{$index}.material_id",
                        "Material purpose '{$material->purpose}' does not match component type '{$type}'."
                    );
                }

                if ($type === 'board' && $material->stock_unit !== 'm2') {
                    $validator->errors()->add(
                        "components.{$index}.material_id",
                        'Board materials must be measured in m2.'
                    );
                }

                if (in_array($type, ['leg', 'handle'], true) && $material->stock_unit !== 'ea') {
                    $validator->errors()->add(
                        "components.{$index}.material_id",
                        'Leg and handle materials must be measured in ea.'
                    );
                }

                if ($type === 'board' && $shape && ! in_array($shape, ['rectangular', 'circular'], true)) {
                    $validator->errors()->add("components.{$index}.shape", 'Invalid board shape.');
                }

                if ($type === 'leg' && $shape && ! in_array($shape, ['cube_foot', 'angular_foot', 'round_foot', 'rectangular_leg', 'cylinder_leg'], true)) {
                    $validator->errors()->add("components.{$index}.shape", 'Invalid leg shape.');
                }

                if ($type === 'handle' && $shape && ! in_array($shape, ['knob', 'bulb_knob', 'bar'], true)) {
                    $validator->errors()->add("components.{$index}.shape", 'Invalid handle shape.');
                }
            }
        });
    }
}
